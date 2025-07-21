import os
from dotenv import load_dotenv

# Carregar variáveis de ambiente
load_dotenv()

def print_config():
    """Exibe as configurações de conexão"""
    print("=== Configurações de Conexão ===")
    print(f"Usuário: {os.getenv('MYSQL_USER')}")
    print(f"Host: {os.getenv('MYSQL_HOST')}")
    print(f"Porta: {os.getenv('MYSQL_PORT')}")
    print(f"Database: {os.getenv('MYSQL_DATABASE')}")
    print(f"Senha: {'*' * len(os.getenv('MYSQL_PASSWORD', '')) if os.getenv('MYSQL_PASSWORD') else 'Não definida'}")
    print("================================")

# Configurações da API Omie
NIMBUS_APP_KEY = os.getenv('NIMBUS_APP_KEY')
NIMBUS_APP_SECRET = os.getenv('NIMBUS_APP_SECRET')

# Configurações do MySQL
MYSQL_HOST = os.getenv('MYSQL_HOST')
if not MYSQL_HOST or MYSQL_HOST.strip() == '':
    MYSQL_HOST = 'localhost'

MYSQL_USER = os.getenv('MYSQL_USER', 'root')
MYSQL_PASSWORD = os.getenv('MYSQL_PASSWORD', '')
MYSQL_DATABASE = os.getenv('MYSQL_DATABASE', 'omie_central')
MYSQL_PORT = int(os.getenv('MYSQL_PORT', '3306'))

# Configurações de delay para APIs
API_DELAY_SECONDS = int(os.getenv('API_DELAY_SECONDS', '0'))
API_DELAY_MINUTES = int(os.getenv('API_DELAY_MINUTES', '0'))
API_DELAY_HOURS = int(os.getenv('API_DELAY_HOURS', '0'))
API_DELAY_DAYS = int(os.getenv('API_DELAY_DAYS', '0'))
API_DELAY_MILLISECONDS = int(os.getenv('API_DELAY_MILLISECONDS', '10'))
