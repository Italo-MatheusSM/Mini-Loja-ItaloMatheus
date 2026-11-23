# Casa do Grão

Mini loja de cafés especiais e acessórios, com catálogo inicial, cadastro de produtos e área para clientes do clube Casa do Grão.

## Requisitos

- PHP 8.0 ou mais recente.
- Extensão PDO_SQLite habilitada.

No Windows, o XAMPP inclui o PHP. Abra o **XAMPP Shell** e confira as extensões com:

```cmd
php -v
php -m | findstr /I "PDO SQLite"
```

O resultado deve incluir `PDO` e `pdo_sqlite`.

## Iniciar no Windows

1. Abra o XAMPP Shell.
2. Entre na pasta do projeto. Ajuste o caminho se a pasta estiver em outro local:

   ```cmd
   cd /d "C:\Users\Italo\Desktop\Mini-Loja-main"
   ```

3. Confirme que `Index.html` está nessa pasta:

   ```cmd
   dir Index.html
   ```

4. Inicie o servidor PHP:

   ```cmd
   php -S 127.0.0.1:8000
   ```

5. Mantenha o terminal aberto e acesse **http://127.0.0.1:8000/Index.html** no navegador. Para encerrar o servidor, volte ao terminal e pressione `Ctrl+C`.

> Acesse `/Index.html`, não somente `/`. A página inicial do projeto tem esse nome.

## Dados e cadastros

Os cafés, acessórios e clientes cadastrados são salvos em SQLite. Por padrão, o banco fica fora da pasta pública do site, em `../Mini-Loja-data/loja.sqlite`. Para definir outro caminho, configure a variável de ambiente `MINI_LOJA_DB_PATH` antes de iniciar o servidor.

O cadastro de cliente pede nome, CPF e e-mail. O CPF deve conter 11 números e não pode estar repetido. Produtos e clientes podem ser removidos na guia **Gerenciar**. O catálogo usa ilustrações vetoriais locais, sem dependência de imagens externas.

Esta aplicação foi preparada para uso local. Antes de disponibilizá-la na internet, configure autenticação e proteção adequada para os dados pessoais dos clientes.
