import requests
import time
import pandas as pd
from pandas import json_normalize
from config import NIMBUS_APP_KEY, NIMBUS_APP_SECRET, API_DELAY_SECONDS, API_DELAY_MINUTES, API_DELAY_HOURS, API_DELAY_DAYS, API_DELAY_MILLISECONDS

class OmieAPI:
    def __init__(self):
        self.app_key = NIMBUS_APP_KEY
        self.app_secret = NIMBUS_APP_SECRET
        self.base_url = "https://app.omie.com.br/api/v1/"
        # Calcular delay total em segundos
        self.delay_seconds = (
            API_DELAY_DAYS * 24 * 60 * 60 + 
            API_DELAY_HOURS * 60 * 60 + 
            API_DELAY_MINUTES * 60 + 
            API_DELAY_SECONDS +
            API_DELAY_MILLISECONDS / 1000.0  # Converter ms para segundos
        )
    
    def fazer_requisicao(self, endpoint, call, parametros):
        """Faz uma requisição para a API da Omie"""
        url = f"{self.base_url}{endpoint}"
        headers = {"Content-Type": "application/json"}

        body = {
            "call": call,
            "app_key": self.app_key,
            "app_secret": self.app_secret,
            "param": [parametros]
        }

        response = requests.post(url, headers=headers, json=body)
        print("Resposta bruta da API Omie:")
        print(response.text)
        response.raise_for_status()
        return response.json()
    
    def buscar_todas_paginas(self, endpoint, call, parametros, data_key, registros_por_pagina=100):
        """Busca todas as páginas de uma consulta paginada"""
        parametros_paginacao = parametros.copy()
        # Lógica especial para cada endpoint
        if endpoint == "financas/mf":
            parametros_paginacao["nRegPorPagina"] = registros_por_pagina
            parametros_paginacao["nPagina"] = 1
        elif "contapagar" in endpoint or "contareceber" in endpoint:
            parametros_paginacao["registros_por_pagina"] = registros_por_pagina
            parametros_paginacao["pagina"] = 1
        elif (
            "geral/clientes" in endpoint or
            "geral/contacorrente" in endpoint or
            "geral/categorias" in endpoint or
            "geral/departamentos" in endpoint
        ):
            parametros_paginacao["registros_por_pagina"] = registros_por_pagina
            parametros_paginacao["pagina"] = 1
        else:
            parametros_paginacao["nRegPorPagina"] = registros_por_pagina
            parametros_paginacao["nPagina"] = 1

        # Buscar primeira página para saber o total
        primeira_pagina = self.fazer_requisicao(endpoint, call, parametros_paginacao)

        # Determinar total de páginas baseado no endpoint
        if endpoint == "financas/mf":
            total_paginas = int(primeira_pagina.get('nTotPaginas', 1))
        elif "contapagar" in endpoint or "contareceber" in endpoint:
            total_paginas = int(primeira_pagina.get('total_de_paginas', 1))
        elif (
            "geral/clientes" in endpoint or
            "geral/contacorrente" in endpoint or
            "geral/categorias" in endpoint or
            "geral/departamentos" in endpoint
        ):
            total_paginas = int(primeira_pagina.get('total_de_paginas', 1))
        else:
            total_paginas = int(primeira_pagina.get('nTotPaginas', 1))

        todos_os_registros = []

        for page in range(1, total_paginas + 1):
            print(f'Baixando página {page} de {total_paginas}')

            # Atualizar número da página
            if (
                "contapagar" in endpoint or
                "contareceber" in endpoint or
                "geral/clientes" in endpoint or
                "geral/contacorrente" in endpoint or
                "geral/categorias" in endpoint or
                "geral/departamentos" in endpoint
            ):
                parametros_paginacao["pagina"] = page
            else:
                parametros_paginacao["nPagina"] = page

            dados = self.fazer_requisicao(endpoint, call, parametros_paginacao)

            if data_key in dados:
                todos_os_registros.extend(dados[data_key])

            # Aplicar delay configurável
            if self.delay_seconds > 0 and page < total_paginas:  # Não fazer delay na última página
                time.sleep(self.delay_seconds)
        
        return todos_os_registros
