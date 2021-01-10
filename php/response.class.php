<?php
class Response{
    protected static $data = array(
        'Code' => 200,
        'Data' => null,
        'Message' => null
    );
    public static function setCode($code){
        self::$data['Code'] = $code;
    }
    public static function getCode(){
        return self::$data['Code'];
    }
    public static function getMessage(){
        return self::$data['Message'];
    }
    public static function setMessage($msg){
        self::$data['Message'] = $msg;
    }

    public static function getData(){
        return self::$data['Data'];
    }
    public static function setData($value){
        self::$data['Data'] = $value;
    }
    public static function setError($msg, $code, $key=false){
        self::$data['Message'] = $msg;
        self::$data['Code'] = $code;
        if($key){
            self::build($key);
        }
    }
    public static function build($key){
        //header("Content-Type: application/json");
        header("Content-Type: text/plain");
        //exit(json_encode(utf8ize(self::$data)));
        exit(Crypt::encode(json_encode(utf8ize(self::$data)), $key));
    }
}
