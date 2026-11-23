<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || strlen($rawBody) > 16384) {
        respond(400, ['error' => 'A solicitação está vazia ou excede o tamanho permitido.']);
    }

    try {
        $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        respond(400, ['error' => 'Os dados enviados não são um JSON válido.']);
    }

    if (!is_array($body)) {
        respond(400, ['error' => 'O formato dos dados enviados é inválido.']);
    }

    return $body;
}

function requiredText(array $data, string $field, int $maxLength, string $label): string
{
    $value = $data[$field] ?? null;
    if (!is_string($value)) {
        respond(422, ['error' => "Informe $label."]);
    }

    $value = trim($value);
    if ($value === '' || preg_match('/^.{1,' . $maxLength . '}$/us', $value) !== 1) {
        respond(422, ['error' => "$label deve ter entre 1 e $maxLength caracteres."]);
    }

    return $value;
}

function formatCpf(string $cpf): string
{
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

try {
    $configuredPath = getenv('MINI_LOJA_DB_PATH');
    $databasePath = $configuredPath !== false && $configuredPath !== ''
        ? $configuredPath
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Mini-Loja-data' . DIRECTORY_SEPARATOR . 'loja.sqlite';
    $databaseDirectory = dirname($databasePath);

    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0770, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('Não foi possível criar o diretório do banco de dados.');
    }

    $database = new PDO('sqlite:' . $databasePath);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            price_cents INTEGER NOT NULL CHECK (price_cents > 0),
            description TEXT NOT NULL,
            illustration TEXT NOT NULL
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            cpf TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL
        )'
    );
    $database->exec('CREATE TABLE IF NOT EXISTS app_settings (setting TEXT PRIMARY KEY, value TEXT NOT NULL)');

    $database->beginTransaction();
    $seedMarker = $database->prepare(
        "INSERT OR IGNORE INTO app_settings (setting, value) VALUES ('casa_grao_catalog_seeded', '1')"
    );
    $seedMarker->execute();
    if ($seedMarker->rowCount() > 0) {
        $legacySeed = $database->query(
            "SELECT value FROM app_settings WHERE setting = 'catalog_seeded'"
        )->fetchColumn();
        if ($legacySeed !== false) {
            $legacyProducts = [
                ['Camiseta básica', 'Camiseta confortável para todos os dias.'],
                ['Caneca clássica', 'Caneca de cerâmica para sua bebida favorita.'],
                ['Caderno pautado', 'Caderno para anotações, estudos e ideias.'],
                ['Garrafa térmica', 'Garrafa prática para acompanhar sua rotina.'],
                ['Ecobag', 'Sacola reutilizável, leve e versátil.'],
                ['Estojo', 'Estojo compacto para organizar seus materiais.'],
                ['Boné casual', 'Boné ajustável para um visual descontraído.'],
                ['Chaveiro', 'Um acessório simples para suas chaves.'],
                ['Agenda semanal', 'Planeje seus compromissos durante a semana.'],
                ['Mochila urbana', 'Mochila espaçosa para trabalho, estudo ou passeio.']
            ];
            $removeLegacyProduct = $database->prepare(
                'DELETE FROM products WHERE id <= 10 AND name = ? AND description = ?'
            );
            foreach ($legacyProducts as $product) {
                $removeLegacyProduct->execute($product);
            }
        }

        $starterProducts = [
            ['Café Mantiqueira', 4290, 'Chocolate ao leite, caramelo e um final doce. 250 g · Sul de Minas', 'coffee'],
            ['Café Cerrado', 3890, 'Notas de castanha e rapadura, corpo aveludado. 250 g · Minas Gerais', 'beans'],
            ['Microlote Mogiana', 5590, 'Frutas amarelas e mel em uma torra delicada. 250 g · São Paulo', 'coffee'],
            ['Descafeinado da Casa', 4690, 'Doçura de cacau e baunilha, sem abrir mão do sabor. 250 g', 'beans'],
            ['Kit coado perfeito', 8990, 'Suporte de cerâmica e filtros para preparar com calma.', 'dripper'],
            ['Caneca Grão', 3490, 'Cerâmica artesanal para acompanhar sua pausa favorita.', 'mug'],
            ['Café fermentado', 6290, 'Aromas de frutas vermelhas e uma doçura marcante. 200 g', 'coffee'],
            ['Coador de cerâmica', 7490, 'Extração uniforme para um café coado cheio de personalidade.', 'dripper'],
            ['Blend Espresso', 4490, 'Chocolate amargo e notas de amêndoa, ideal para espresso. 250 g', 'beans'],
            ['Café da semana', 3290, 'Uma nova origem selecionada pela nossa equipe a cada semana. 250 g', 'coffee']
        ];
        $insertProduct = $database->prepare(
            'INSERT INTO products (name, price_cents, description, illustration) VALUES (?, ?, ?, ?)'
        );
        foreach ($starterProducts as $product) {
            $insertProduct->execute($product);
        }
    }
    $database->commit();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $products = $database->query(
            'SELECT id, name, price_cents, description, illustration FROM products ORDER BY id'
        )->fetchAll();
        foreach ($products as &$product) {
            $product['id'] = (int) $product['id'];
            $product['price'] = (int) $product['price_cents'] / 100;
            unset($product['price_cents']);
        }
        unset($product);

        $customers = $database->query('SELECT id, name, cpf, email FROM customers ORDER BY id')->fetchAll();
        foreach ($customers as &$customer) {
            $customer['id'] = (int) $customer['id'];
            $customer['cpf'] = formatCpf($customer['cpf']);
        }
        unset($customer);

        respond(200, ['products' => $products, 'customers' => $customers]);
    }

    if ($method === 'POST') {
        $body = readJsonBody();
        $type = $body['type'] ?? '';

        if ($type === 'product') {
            $name = requiredText($body, 'name', 80, 'o nome do produto');
            $description = requiredText($body, 'description', 240, 'a descrição do produto');
            $price = $body['price'] ?? null;
            if (!is_numeric($price) || !is_finite((float) $price) || (float) $price <= 0 || (float) $price > 9999999.99) {
                respond(422, ['error' => 'Informe um preço maior que zero e menor que R$ 10.000.000,00.']);
            }

            $priceCents = (int) round((float) $price * 100);
            $insert = $database->prepare(
                'INSERT INTO products (name, price_cents, description, illustration) VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$name, $priceCents, $description, 'coffee']);
            respond(201, ['id' => (int) $database->lastInsertId()]);
        }

        if ($type === 'customer') {
            $name = requiredText($body, 'name', 100, 'o nome do cliente');
            $email = requiredText($body, 'email', 120, 'o e-mail');
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                respond(422, ['error' => 'Informe um e-mail válido.']);
            }
            $cpfValue = $body['cpf'] ?? null;
            if (!is_string($cpfValue)) {
                respond(422, ['error' => 'Informe um CPF com 11 números.']);
            }
            $cpf = preg_replace('/\D/', '', $cpfValue);
            if (strlen($cpf) !== 11) {
                respond(422, ['error' => 'Informe um CPF com 11 números.']);
            }

            $insert = $database->prepare('INSERT INTO customers (name, cpf, email) VALUES (?, ?, ?)');
            $insert->execute([$name, $cpf, $email]);
            respond(201, ['id' => (int) $database->lastInsertId()]);
        }

        respond(400, ['error' => 'Tipo de cadastro inválido.']);
    }

    if ($method === 'DELETE') {
        $type = $_GET['type'] ?? '';
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($type, ['product', 'customer'], true) || $id === false || $id < 1) {
            respond(400, ['error' => 'O cadastro solicitado para remoção é inválido.']);
        }

        $table = $type === 'product' ? 'products' : 'customers';
        $delete = $database->prepare("DELETE FROM $table WHERE id = ?");
        $delete->execute([$id]);
        if ($delete->rowCount() === 0) {
            respond(404, ['error' => 'O cadastro não foi encontrado.']);
        }

        respond(200, ['deleted' => true]);
    }

    header('Allow: GET, POST, DELETE');
    respond(405, ['error' => 'Método não permitido.']);
} catch (PDOException $error) {
    error_log('Falha no banco da loja: ' . $error->getMessage());
    if ($error->getCode() === '23000') {
        respond(409, ['error' => 'Este CPF já está cadastrado.']);
    }
    respond(500, ['error' => 'Não foi possível acessar o banco de dados. Verifique a configuração do PHP e do SQLite.']);
} catch (Throwable $error) {
    error_log('Falha na API da loja: ' . $error->getMessage());
    respond(500, ['error' => 'Ocorreu um erro interno ao processar a solicitação.']);
}