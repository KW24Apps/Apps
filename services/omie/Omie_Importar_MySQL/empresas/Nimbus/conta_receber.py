import pandas as pd
from omie_api import OmieAPI
from database_utils import salvar_com_fallback

# Função principal do ETL de contas a receber

def etl_conta_receber_nimbus():
    api = OmieAPI()
    print('Iniciando ETL de contas a receber Nimbus...')
    # Parâmetros iniciais da API
    parametros = {
        'pagina': 1,
        'registros_por_pagina': 100,
        'apenas_importado_api': 'N'
    }
    # Buscar todos os registros paginados
    registros = api.buscar_todas_paginas(
        endpoint='financas/contareceber/',
        call='ListarContasReceber',
        parametros=parametros,
        data_key='conta_receber_cadastro',
        registros_por_pagina=100
    )
    print(f'Total de registros recebidos: {len(registros)}')
    # Normalizar e expandir os dados (ajustar conforme modelo Power BI)
    df = pd.json_normalize(registros, sep='.')
    # Adicionar colunas fixas da empresa
    import os
    empresa_nome = os.getenv('EMPRESA_NOME', 'Nimbus')
    empresa_cnpj = os.getenv('EMPRESA_CNPJ', '51.010.674/0001-08')
    df['Minha Empresa Nome'] = empresa_nome
    df['Minha Empresa CNPJ'] = empresa_cnpj
    # Salvar no MySQL com fallback, nome da tabela dinâmico por empresa
    tabela = f"conta_receber_{empresa_nome.lower()}"
    salvar_com_fallback(df, tabela)
    print('ETL de contas a receber finalizado.')

if __name__ == '__main__':
    etl_conta_receber_nimbus()
