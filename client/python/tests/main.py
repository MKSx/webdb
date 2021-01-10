import requests, json, argparse, sys
from crypt import crypt
from prettytable import PrettyTable
from tqdm import tqdm
from uuid import uuid4
import os

from requests.packages.urllib3.exceptions import InsecureRequestWarning
requests.packages.urllib3.disable_warnings(InsecureRequestWarning)
requests.packages.urllib3.util.ssl_.DEFAULT_CIPHERS += ':HIGH:!DH:!aNULL'

#https://stackoverflow.com/a/41041028/13886183
try:
    requests.packages.urllib3.contrib.pyopenssl.util.ssl_.DEFAULT_CIPHERS += ':HIGH:!DH:!aNULL'
except AttributeError:
    pass

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--url', type=str, help='URL da aplicação', default='http://localhost/index.pdo.php')
    parser.add_argument('--key', type=str, help='Chave que será usada para criptografar os dados', default='XOR KEY')
    parser.add_argument('--token', type=str, help='Token de autenticação', default='AUTH TOKEN')
    parser.add_argument('--dbms', type=str, help='Tipo do banco de dados', default='mysql')
    parser.add_argument('--host', type=str, help='Ip do banco de daods', default='127.0.0.1')
    parser.add_argument('--port', type=int, help='Porta do banco de dados', default=3307)
    parser.add_argument('--dbname', type=str, help='Nome do banco de dados', required=True)
    parser.add_argument('--user', type=str, help='Nome do usuário', default='root')
    parser.add_argument('--pwd', type=str, help='Senha do usuário', default='')

    args = parser.parse_args()

    crypt.setKey(args.key)

    session =  requests.Session()

    def send(data, download=False):
        try:

            res = session.post(args.url, data={
                'd': crypt.encode(json.dumps(data))
            }, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36'}, allow_redirects=False, verify=False, stream=download)

            # Em caso de erro desconhecido
            if (res.status_code != 201 and download) or (not download and res.status_code != 200):
                print('Status code:', res.status_code, '-', res.reason)
                print(res.text)
                exit()

            if not res.headers.get('Content-Type').startswith('text/plain'):
                print(res.text)
                print('Conteúdo da resposta inválido')
                exit()

            if download:
                length = int(res.headers.get('content-length', 0))
                name = res.headers.get('Content-Disposition', 'filename=' + str(uuid4()) + '.txt').replace('filename=', '')
                tmp = str(uuid4()) + '.tmp'

                with open(tmp, 'wb') as file, tqdm(desc=name, total=length, unit='iB', unit_scale=True, unit_divisor=1024) as bar:
                    for data in res.iter_content(chunk_size=1024):
                        size = file.write(data)
                        bar.update(size)
                content= None
                with open(tmp, 'r') as file:
                    content = json.loads(crypt.decode(file.read()))

                os.remove(tmp)

                if not content:
                    print("Falha ao decodar o conteúdo do download")
                    exit()
                
                table = PrettyTable()
                table.field_names = content['Result']['Columns']
                table.add_rows(content['Result']['Rows'])
                table.align = "l"
                with open(name, 'w', encoding='utf-8') as file:
                    file.write(str(table))
                
                return name
            
            return json.loads(crypt.decode(res.text))
            
        except Exception as e:
            print(e)
            exit()

    def auth():
        data = send({
            'Auth': args.token
        })

        return data['Code'] == 200, data['Message']

    def connect():
        data = send({
            'Connect': {
                'Driver': args.dbms,
                'Host': args.host,
                'Port': args.port,
                'DBName': args.dbname,
                'User': args.user,
                'Pass': args.pwd
            }
        })

        return (data['Code'] == 200, data['Message'])
    
    download_file = False

    def execute(query):
        queryPart = query.lower().split(' ', 1)[0]
        queryType = 0
        if queryPart in ['create', 'upload', 'delete',' drop', 'truncate', 'alter', 'backup']:
            queryType = 1 #affected rows
        elif queryPart in ['insert']:
            queryPart = 2 #last insert
        
        data = send({
            'Execute': {
                'Query': query,
                'Download': download_file,
                'Type': queryType
            }
        }, download_file)

        return (data, queryType) if not download_file else (data, queryType)

    status, message = auth()

    if not status:
        print(message)
        return False
    
    status, message = connect()

    if not status:
        print(message)
        return False

    
    while True:
        query = input(args.dbname + '@' + args.host + '$ ').strip()
        if len(query) < 1:
            continue

        cmd = query.lower()

        if cmd == 'exit':
            print('bye')
            return True
        elif cmd == 'download on':
            download_file = True
            print('Download on')
            continue
        elif cmd == 'download off':
            download_file = False
            print('Download off')
            continue
        
        data, tipo = execute(query)

        if download_file:
            print("Salvo em " + data)
            continue

        if data['Code'] != 200:
            print(data['Message'])
        else:
            if tipo != 0:
                if tipo == 1:
                    print(data['Data'], 'linhas afetadas')
                else:
                    print(data['Data']['AffectedRows'], 'linhas afetadas')
                    print('ùltimo id inserido', data['Data']['LastId'])
            else:
                if data['Data']['Count'] < 1:
                    print(data)
                    print("Nenhum registro encontrado")
                else:
                    table = PrettyTable()
                    table.field_names = data['Data']['Result']['Columns']
                    table.add_rows(data['Data']['Result']['Rows'])
                    table.align = "l"
                    print(table)
                    print(data['Data']['Count'], 'resultados')
            
if __name__ == '__main__':
    main()

