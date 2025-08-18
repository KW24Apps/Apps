# Lista de arquivos que devem ser removidos do servidor
# Execute: git clean -fd para remover arquivos não rastreados

# Arquivos de debug removidos que podem ainda estar no servidor:
public/form/debug_*.php
public/form/test_*.php
public/form/simulate_request.php
public/form/WebhookHelper.php
public/form/setup.php
public/form/check_*.php
public/form/show_tables.php
public/form/database.sql
public/form/config_secure.php.example
public/form/README_OLD.md

# Para remover no servidor, execute:
# find . -name "debug_*.php" -delete
# find . -name "test_*.php" -delete
