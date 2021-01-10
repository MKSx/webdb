<?php



session_start();

class WebDB{
    private static $key;
    private static $config;
    private static $pdo;
    private static $input;
    public static function init($token, $key){
        self::$key = $key;


        if(empty($_POST['d'])){
            Response::setError('Informe um json', 400, self::$key);
        }
        $data = json_decode(Crypt::decode($_POST['d'], self::$key), true);
        if(json_last_error()){
            Response::setError('Erro ao decodar o json', 400, self::$key);
        }
        //var_dump($data);
        if(empty($data)){
            Response::setError('Json mal formado', 400, self::$key);
        }
        self::$input = $data;
        
        self::auth($token);

        if(!is_string($_SESSION['config']) || !empty(self::$input['Connect'])){
            if(empty(self::$input['Connect'])){
                Response::setError("Autenticado com sucesso!", 200, self::$key);
            }
            if(!self::verifyConfigKeys(self::$input['Connect'])){
                Response::setError("Informações necessárias não enviadas para se conectar ao banco de dados", 400, self::$key);
            }
            self::$config = self::$input['Connect'];
            $_SESSION['config'] = Crypt::encode(json_encode(utf8ize(self::$input['Connect'])), self::$key);
        }
        else{
            self::$config = json_decode(Crypt::decode($_SESSION['config'], self::$key), true);
        }

        if(!self::connect()){
            Response::build($key);
        }
        
        if(isset(self::$input['Execute'])){
            if(empty(self::$input['Execute']['Query']) || !is_string(self::$input['Execute']['Query']) || !(
                isset(self::$input['Execute']['Type']) && is_integer(self::$input['Execute']['Type']) && 
                isset(self::$input['Execute']['Download']) && is_bool(self::$input['Execute']['Download'])
            )){
                
                Response::setError('Json Execute inválido', 400, self::$key);
            }
            $result = self::$pdo->query(self::$input['Execute']['Query']);
            if($result){
                switch(self::$input['Execute']['Type']){
                    case 1:{
                        Response::setData($result->rowCount());
                        break;
                    }
                    case 2:{
                        Response::setData(array(
                            'LastId' => self::$pdo->lastInsertId(),
                            'AffectedRows' => $result->rowCount()
                        ));
                        break;
                    }
                    case 0:{
                        $data = array(
                            'Count' => $result->rowCount(),
                            'Result' => null
                        );
                        $row = $result->fetch(PDO::FETCH_ASSOC);
                        
                        if($data['Count'] > 0 || ($data['Count'] === 0 && $row)){
                            $data['Result'] = array('Columns' => array(), 'Rows' => array());
                            
                            if($row){
                                $count = 1;
                                $values = array();
                                foreach($row as $name => $value){
                                    $data['Result']['Columns'][] = $name;
                                    $values[] = $value;
                                }
                                $data['Result']['Rows'][] = $values;//$result->fetchAll(PDO::FETCH_NUM);
                                while(($val = $result->fetch(PDO::FETCH_NUM)) !== false){
                                    $count++;
                                    $data['Result']['Rows'][] = $val;
                                }
                                $data['Count'] = $count;
                                
                            }
                            if(self::$input['Execute']['Download']){
                                http_response_code(201);
                                header('Cache-control: private');
                                header('Content-Type: text/plain');
                                //header('Content-Type: application/octet-stream');
                                header('Content-Disposition: filename='.md5(uniqid(rand(), true)).'.txt');

                                exit(Crypt::encode(json_encode(utf8ize($data)), self::$key));
                            }
                        }
                        Response::setData($data);
                        break;
                    }
                    default:{
                        Response::setError('Query Type inválido', 400);
                        break;
                    }
                }
            }
            else{
                Response::setError(self::$pdo->errorInfo()[2], 500);
            }
        }
        else{
            Response::setMessage('Conectado com sucesso!');
        }
        Response::build($key);
    }


    private static function connect(){
        if(!self::$pdo && self::$config){
            try{
                self::$pdo = new PDO(self::$config['Driver'].':host='.self::$config['Host'].';port='.self::$config['Port'].';dbname='.self::$config['DBName'], self::$config['User'], self::$config['Pass']);
                self::$pdo->exec('set names utf8');
            }
            catch(PDOException $e){
                self::$pdo = false;
                Response::setError($e->getMessage(), 406);
                return false;
            }
        }
        return true;
    }
    private static function auth($token){
        if(isset($_SESSION['config'])){
            self::$config = $_SESSION['config'] !== true ? json_decode(Crypt::decode($_SESSION['config'], self::$key)) : null;
        }
        else{
            if(empty(self::$input['Auth']) || self::$input['Auth'] !== $token){
                Response::setError('Não autorizado', 401, self::$key);
            }
            $_SESSION['config'] = true;
        }
        return true;
    }
    private static function verifyConfigKeys($config){
        if(!is_array($config)){
            return false;
        }
        $keys = array('Driver', 'Host', 'Port', 'DBName', 'User', 'Pass');
        
        foreach($keys as $key){
            if(!isset($config[$key])){
                return false;
            }
        }
        return true;
    }

}
WebDB::init('AUTH TOKEN', 'XOR KEY');
