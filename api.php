<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

const OCTOVIEW_JWT_ALG = 'HS256';
const OCTOVIEW_JWT_TTL = 28800;

try {
    $db = octoview_db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? 'bootstrap';
    $input = read_json_input();

    switch ($action) {
        case 'login':
            ensure_method($method, 'POST');
            respond(['ok' => true, 'data' => login_user($db, $input)]);
            break;

        case 'forgot-password':
            ensure_method($method, 'POST');
            respond(['ok' => true, 'data' => forgot_password($db, $input)]);
            break;

        case 'bootstrap':
            $auth = require_auth($db);
            respond(['ok' => true, 'data' => bootstrap_payload($db, $auth)]);
            break;

        case 'filament-save':
            ensure_method($method, 'POST');
            require_admin($db);
            save_filament($db, $input);
            respond(['ok' => true, 'data' => bootstrap_payload($db, require_auth($db))]);
            break;

        case 'filament-delete':
            ensure_method($method, 'POST');
            require_admin($db);
            delete_filament($db, (int) ($input['id'] ?? 0));
            respond(['ok' => true, 'data' => bootstrap_payload($db, require_auth($db))]);
            break;

        case 'model-save':
            ensure_method($method, 'POST');
            require_admin($db);
            save_model($db, $input);
            respond(['ok' => true, 'data' => bootstrap_payload($db, require_auth($db))]);
            break;

        case 'model-delete':
            ensure_method($method, 'POST');
            require_admin($db);
            delete_model($db, (int) ($input['id'] ?? 0));
            respond(['ok' => true, 'data' => bootstrap_payload($db, require_auth($db))]);
            break;

        case 'order-create':
            ensure_method($method, 'POST');
            $auth = require_auth($db);
            create_order($db, $auth, $input);
            respond(['ok' => true, 'data' => bootstrap_payload($db, $auth)]);
            break;

        case 'order-status':
            ensure_method($method, 'POST');
            require_admin($db);
            update_order_status($db, $input);
            respond(['ok' => true, 'data' => bootstrap_payload($db, require_auth($db))]);
            break;

        case 'user-photo-save':
            ensure_method($method, 'POST');
            $auth = require_auth($db);
            save_user_photo($db, $auth, $input);
            respond(['ok' => true, 'data' => bootstrap_payload($db, $auth)]);
            break;

        case 'user-create':
            ensure_method($method, 'POST');
            require_admin($db);
            $created = create_user($db, $input);
            respond([
                'ok' => true,
                'data' => [
                    'created' => $created,
                    'bootstrap' => bootstrap_payload($db, require_auth($db)),
                ],
            ]);
            break;

        case 'user-update':
            ensure_method($method, 'POST');
            require_admin($db);
            update_user($db, $input);
            respond(['ok' => true, 'data' => bootstrap_payload($db, require_auth($db))]);
            break;

        case 'user-inactivate':
            ensure_method($method, 'POST');
            $admin = require_admin($db);
            inactivate_user($db, (int) ($input['id'] ?? 0), $admin);
            respond(['ok' => true, 'data' => bootstrap_payload($db, require_auth($db))]);
            break;

        default:
            throw new InvalidArgumentException('Acao invalida.');
    }
} catch (Throwable $exception) {
    http_response_code($exception instanceof RuntimeException && $exception->getCode() >= 400 ? $exception->getCode() : 400);
    respond([
        'ok' => false,
        'error' => $exception->getMessage(),
    ]);
}

function respond(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_method(string $actual, string $expected): void
{
    if (strtoupper($actual) !== strtoupper($expected)) {
        throw new InvalidArgumentException('Metodo HTTP invalido.');
    }
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function bootstrap_payload(mysqli $db, array $authUser): array
{
    return [
        'currentUser' => $authUser,
        'units' => fetch_units($db),
        'users' => fetch_users($db, $authUser),
        'filaments' => fetch_filaments($db, $authUser),
        'models' => fetch_models($db),
        'orders' => fetch_orders($db, $authUser),
        'history' => fetch_history($db, $authUser),
        'sectors' => fetch_sectors($db),
    ];
}

function login_user(mysqli $db, array $input): array
{
    ensure_auth_columns($db);

    $username = trim((string) ($input['usuario'] ?? ''));
    $password = (string) ($input['senha'] ?? '');
    if ($username === '' || $password === '') {
        throw new InvalidArgumentException('Informe usuario e senha.');
    }

    $stmt = $db->prepare(
        'SELECT s.id, s.usuario, s.matricula, s.senha_hash, s.nome, s.email, s.tipo_usuario, s.escopo_acesso, s.unit_id, s.foto_perfil, COALESCE(s.ativo, 1) AS ativo, st.nome_setor, st.nivel_prioridade, u.nome_unidade, u.cidade, u.estado, u.codigo_senac
         FROM Solicitante s
         LEFT JOIN Setor st ON st.id = s.setor_id
         LEFT JOIN Unidade u ON u.id = s.unit_id
         WHERE s.usuario = ? OR s.email = ? OR s.matricula = ?
         LIMIT 1'
    );
    $stmt->bind_param('sss', $username, $username, $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || empty($row['senha_hash']) || !password_verify($password, $row['senha_hash'])) {
        throw http_error('Usuario ou senha invalidos.', 401);
    }
    if ((int) ($row['ativo'] ?? 1) !== 1) {
        throw http_error('Este usuario esta inativo.', 403);
    }

    $user = map_user_row($row);
    return [
        'token' => create_jwt($user),
        'user' => $user,
        'expiresIn' => OCTOVIEW_JWT_TTL,
    ];
}

function forgot_password(mysqli $db, array $input): array
{
    ensure_auth_columns($db);

    $identifier = trim((string) ($input['identificador'] ?? ''));
    if ($identifier === '') {
        throw new InvalidArgumentException('Informe usuario, email ou matricula para redefinir a senha.');
    }

    $stmt = $db->prepare(
        'SELECT id, usuario
         FROM Solicitante
         WHERE usuario = ? OR email = ? OR matricula = ?
         LIMIT 1'
    );
    $stmt->bind_param('sss', $identifier, $identifier, $identifier);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        throw new InvalidArgumentException('Usuario nao encontrado para redefinicao de senha.');
    }

    $passwordHash = password_hash('1234', PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Nao foi possivel redefinir a senha.');
    }

    $stmt = $db->prepare('UPDATE Solicitante SET senha_hash = ? WHERE id = ?');
    $id = (int) $row['id'];
    $stmt->bind_param('si', $passwordHash, $id);
    $stmt->execute();

    return [
        'usuario' => $row['usuario'],
        'senhaTemporaria' => '1234',
    ];
}

function require_auth(mysqli $db): array
{
    ensure_auth_columns($db);

    $header = get_authorization_header();
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        throw http_error('Sessao nao encontrada. Faca login novamente.', 401);
    }

    $payload = decode_jwt(trim($matches[1]));
    $userId = (int) ($payload['sub'] ?? 0);
    if ($userId <= 0) {
        throw http_error('Token invalido.', 401);
    }

    $stmt = $db->prepare(
        'SELECT s.id, s.usuario, s.matricula, s.nome, s.email, s.tipo_usuario, s.escopo_acesso, s.unit_id, s.foto_perfil, COALESCE(s.ativo, 1) AS ativo, st.nome_setor, st.nivel_prioridade, u.nome_unidade, u.cidade, u.estado, u.codigo_senac
         FROM Solicitante s
         LEFT JOIN Setor st ON st.id = s.setor_id
         LEFT JOIN Unidade u ON u.id = s.unit_id
         WHERE s.id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        throw http_error('Usuario da sessao nao encontrado.', 401);
    }
    if ((int) ($row['ativo'] ?? 1) !== 1) {
        throw http_error('Este usuario esta inativo.', 403);
    }

    return map_user_row($row);
}

function require_admin(mysqli $db): array
{
    $user = require_auth($db);
    if (!user_is_admin($user)) {
        throw http_error('Somente administradores podem executar esta acao.', 403);
    }

    return $user;
}

function create_jwt(array $user): string
{
    $now = time();
    $payload = [
        'sub' => $user['id'],
        'usuario' => $user['usuario'],
        'tipo' => $user['tipo'],
        'perfil' => $user['perfil'],
        'unit_id' => $user['unitId'],
        'iat' => $now,
        'exp' => $now + OCTOVIEW_JWT_TTL,
    ];

    $header = ['typ' => 'JWT', 'alg' => OCTOVIEW_JWT_ALG];
    $encodedHeader = jwt_b64_encode(json_encode($header, JSON_UNESCAPED_UNICODE));
    $encodedPayload = jwt_b64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
    $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, jwt_secret(), true);

    return $encodedHeader . '.' . $encodedPayload . '.' . jwt_b64_encode($signature);
}

function decode_jwt(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw http_error('Token invalido.', 401);
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
    $header = json_decode(jwt_b64_decode($encodedHeader), true);
    $payload = json_decode(jwt_b64_decode($encodedPayload), true);
    $signature = jwt_b64_decode($encodedSignature);
    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? null) !== OCTOVIEW_JWT_ALG) {
        throw http_error('Token invalido.', 401);
    }

    $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, jwt_secret(), true);
    if (!hash_equals($expected, $signature)) {
        throw http_error('Token invalido.', 401);
    }

    if ((int) ($payload['exp'] ?? 0) < time()) {
        throw http_error('Sessao expirada. Faca login novamente.', 401);
    }

    return $payload;
}

function jwt_secret(): string
{
    return getenv('OCTOVIEW_JWT_SECRET') ?: 'octoview-dev-secret-change-me';
}

function jwt_b64_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function jwt_b64_decode(string $value): string
{
    $pad = strlen($value) % 4;
    if ($pad > 0) {
        $value .= str_repeat('=', 4 - $pad);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw http_error('Token invalido.', 401);
    }

    return $decoded;
}

function fetch_users(mysqli $db, array $authUser): array
{
    ensure_auth_columns($db);

    $hasProfilePhoto = column_exists($db, 'Solicitante', 'foto_perfil');
    $sql = <<<SQL
        SELECT
            s.id,
            s.nome,
            s.email,
            s.usuario,
            s.matricula,
            COALESCE(s.tipo_usuario, 'comum') AS tipo_usuario,
            COALESCE(s.escopo_acesso, 'colaborador') AS escopo_acesso,
            COALESCE(s.ativo, 1) AS ativo,
            s.unit_id,
            %s
            st.nome_setor,
            st.nivel_prioridade,
            u.nome_unidade,
            u.cidade,
            u.estado,
            u.codigo_senac
        FROM Solicitante s
        LEFT JOIN Setor st ON st.id = s.setor_id
        LEFT JOIN Unidade u ON u.id = s.unit_id
        %s
        ORDER BY s.nome
    SQL;
    $sql = sprintf(
        $sql,
        $hasProfilePhoto ? 's.foto_perfil,' : 'NULL AS foto_perfil,',
        scope_where_clause('s.unit_id', $authUser)
    );

    $stmt = scoped_prepare($db, $sql, 's.unit_id', $authUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = map_user_row($row);
    }

    return $users;
}

function map_user_row(array $row): array
{
    $perfil = normalize_access_profile((string) ($row['escopo_acesso'] ?? ''), (string) ($row['tipo_usuario'] ?? 'comum'));
    $isAdmin = in_array($perfil, ['admin_local', 'admin_nacional'], true);

    return [
        'id' => (int) $row['id'],
        'nm' => $row['nome'],
        'email' => $row['email'],
        'usuario' => $row['usuario'] ?? '',
        'matricula' => $row['matricula'] ?? '',
        'tipo' => $isAdmin ? 'admin' : 'comum',
        'perfil' => $perfil,
        'ativo' => (int) ($row['ativo'] ?? 1) === 1,
        'setor' => $row['nome_setor'] ?: 'Sem Setor',
        'prioridade' => (int) ($row['nivel_prioridade'] ?? 1),
        'foto' => $row['foto_perfil'] ?: null,
        'unitId' => isset($row['unit_id']) ? (int) $row['unit_id'] : 0,
        'unidade' => $row['nome_unidade'] ?? null,
        'cidadeUnidade' => $row['cidade'] ?? null,
        'estadoUnidade' => $row['estado'] ?? null,
        'codigoSenac' => $row['codigo_senac'] ?? null,
    ];
}

function fetch_sectors(mysqli $db): array
{
    $result = $db->query('SELECT id, nome_setor, nivel_prioridade FROM Setor ORDER BY nome_setor');
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'nome' => $row['nome_setor'],
            'prioridade' => (int) $row['nivel_prioridade'],
        ];
    }

    return $items;
}

function fetch_units(mysqli $db): array
{
    if (!table_exists($db, 'Unidade')) {
        return [];
    }

    $result = $db->query('SELECT id, nome_unidade, cidade, estado, codigo_senac FROM Unidade ORDER BY nome_unidade');
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'nome' => $row['nome_unidade'],
            'cidade' => $row['cidade'],
            'estado' => $row['estado'],
            'codigo' => $row['codigo_senac'],
        ];
    }

    return $items;
}

function fetch_filaments(mysqli $db, array $authUser): array
{
    $sql = <<<SQL
        SELECT
            ef.id,
            ef.cor,
            ef.tipo,
            ef.peso_atual_gramas,
            ef.unit_id,
            COALESCE(ef.cor_hex, '#888888') AS cor_hex,
            GREATEST(
                ef.peso_atual_gramas,
                COALESCE((
                    SELECT MAX(he.quantidade_movimentada)
                    FROM Historico_Estoque he
                    WHERE he.filamento_id = ef.id
                    AND he.tipo_movimentacao = 'Entrada'
                ), 0)
            ) AS capacidade
        FROM Estoque_Filamento ef
        %s
        ORDER BY ef.id
    SQL;
    $sql = sprintf($sql, scope_where_clause('ef.unit_id', $authUser));
    $stmt = scoped_prepare($db, $sql, 'ef.unit_id', $authUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'cor' => $row['cor'],
            'tipo' => $row['tipo'],
            'peso' => (float) $row['peso_atual_gramas'],
            'capacidade' => (float) $row['capacidade'],
            'hex' => $row['cor_hex'],
            'unitId' => (int) ($row['unit_id'] ?? 0),
        ];
    }

    return $items;
}

function fetch_models(mysqli $db): array
{
    $result = $db->query('SELECT id, nome_modelo, descricao, peso_estimado, link_stl, foto_exemplo FROM Galeria_Modelos ORDER BY id');
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'nm' => $row['nome_modelo'],
            'desc' => $row['descricao'] ?? '',
            'peso' => (float) ($row['peso_estimado'] ?? 0),
            'link' => $row['link_stl'] ?? '',
            'foto' => $row['foto_exemplo'] ?: null,
        ];
    }

    return $items;
}

function fetch_orders(mysqli $db, array $authUser): array
{
    $hasPrintDuration = column_exists($db, 'Pedido', 'duracao_impressao_minutos');
    $sql = <<<SQL
        SELECT
            p.id,
            p.solicitante_id,
            p.modelo_id,
            p.filamento_id,
            p.finalidade,
            DATE(p.data_solicitacao) AS data_solicitacao,
            p.data_limite,
            p.prioridade_bandeira,
            p.status,
            p.observacoes,
            p.imagem_referencia,
            p.peso_final_gasto,
            %s
            p.unit_id,
            s.nome AS solicitante_nome,
            st.nome_setor,
            gm.nome_modelo,
            ef.cor,
            ef.tipo,
            u.nome_unidade
        FROM Pedido p
        INNER JOIN Solicitante s ON s.id = p.solicitante_id
        LEFT JOIN Setor st ON st.id = s.setor_id
        LEFT JOIN Galeria_Modelos gm ON gm.id = p.modelo_id
        INNER JOIN Estoque_Filamento ef ON ef.id = p.filamento_id
        LEFT JOIN Unidade u ON u.id = p.unit_id
        %s
        ORDER BY p.data_solicitacao DESC, p.id DESC
    SQL;
    $sql = sprintf(
        $sql,
        $hasPrintDuration ? 'p.duracao_impressao_minutos,' : 'NULL AS duracao_impressao_minutos,',
        scope_where_clause('p.unit_id', $authUser)
    );
    $stmt = scoped_prepare($db, $sql, 'p.unit_id', $authUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'solicitanteId' => (int) $row['solicitante_id'],
            'sol' => $row['solicitante_nome'],
            'setor' => $row['nome_setor'] ?: 'Sem Setor',
            'pri' => (int) ($row['prioridade_bandeira'] ?? 1),
            'mId' => $row['modelo_id'] !== null ? (int) $row['modelo_id'] : null,
            'mod' => $row['nome_modelo'] ?: 'Modelo personalizado',
            'fId' => (int) $row['filamento_id'],
            'fil' => trim($row['cor'] . ' ' . $row['tipo']),
            'fin' => $row['finalidade'] ?? '',
            'data' => $row['data_solicitacao'],
            'dataLimite' => $row['data_limite'],
            'obs' => $row['observacoes'] ?? '',
            'img' => $row['imagem_referencia'] ?: null,
            'pesoGasto' => $row['peso_final_gasto'] !== null ? (float) $row['peso_final_gasto'] : null,
            'duracaoMinutos' => $row['duracao_impressao_minutos'] !== null ? (int) $row['duracao_impressao_minutos'] : null,
            'st' => normalize_status($row['status'] ?? 'Pendente'),
            'unitId' => (int) ($row['unit_id'] ?? 0),
            'unidade' => $row['nome_unidade'] ?? null,
        ];
    }

    return $items;
}

function fetch_history(mysqli $db, array $authUser): array
{
    $sql = <<<SQL
        SELECT
            he.id,
            he.filamento_id,
            he.quantidade_movimentada,
            he.tipo_movimentacao,
            DATE(he.data_movimentacao) AS data_movimentacao,
            he.unit_id,
            ef.cor,
            ef.tipo
        FROM Historico_Estoque he
        INNER JOIN Estoque_Filamento ef ON ef.id = he.filamento_id
        %s
        ORDER BY he.data_movimentacao DESC, he.id DESC
    SQL;
    $sql = sprintf($sql, scope_where_clause('he.unit_id', $authUser));
    $stmt = scoped_prepare($db, $sql, 'he.unit_id', $authUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'filamentoId' => (int) $row['filamento_id'],
            'fil' => trim($row['cor'] . ' ' . $row['tipo']),
            'tipo' => $row['tipo_movimentacao'],
            'qtd' => (float) $row['quantidade_movimentada'],
            'data' => $row['data_movimentacao'],
            'unitId' => (int) ($row['unit_id'] ?? 0),
        ];
    }

    return $items;
}

function save_filament(mysqli $db, array $input): void
{
    $authUser = require_admin($db);
    $id = (int) ($input['id'] ?? 0);
    $cor = trim((string) ($input['cor'] ?? ''));
    $tipo = trim((string) ($input['tipo'] ?? ''));
    $peso = (float) ($input['peso'] ?? -1);
    $hex = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($input['hex'] ?? '')) ? $input['hex'] : '#888888';

    if ($cor === '' || $tipo === '' || $peso < 0) {
        throw new InvalidArgumentException('Dados de filamento invalidos.');
    }

    if ($id > 0) {
        $current = require_scoped_record($db, 'Estoque_Filamento', $id, $authUser);

        $stmt = $db->prepare('UPDATE Estoque_Filamento SET cor = ?, tipo = ?, peso_atual_gramas = ?, cor_hex = ?, status_alerta = ? WHERE id = ?');
        $alert = $peso <= 200 ? 1 : 0;
        $stmt->bind_param('ssdsii', $cor, $tipo, $peso, $hex, $alert, $id);
        $stmt->execute();

        $diff = round($peso - (float) $current['peso_atual_gramas'], 2);
        if ($diff !== 0.0) {
            insert_history($db, $id, abs($diff), $diff > 0 ? 'Entrada' : 'Saida', (int) $current['unit_id']);
        }
        return;
    }

    $stmt = $db->prepare('INSERT INTO Estoque_Filamento (cor, tipo, peso_atual_gramas, status_alerta, cor_hex, unit_id) VALUES (?, ?, ?, ?, ?, ?)');
    $alert = $peso <= 200 ? 1 : 0;
    $unitId = require_user_unit_id($authUser);
    $stmt->bind_param('ssdisi', $cor, $tipo, $peso, $alert, $hex, $unitId);
    $stmt->execute();
    insert_history($db, (int) $db->insert_id, $peso, 'Entrada', $unitId);
}

function delete_filament(mysqli $db, int $id): void
{
    $authUser = require_admin($db);
    if ($id <= 0) {
        throw new InvalidArgumentException('Filamento invalido.');
    }

    require_scoped_record($db, 'Estoque_Filamento', $id, $authUser);

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM Pedido WHERE filamento_id = ? AND status <> 'Cancelado'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    if ($total > 0) {
        throw new InvalidArgumentException('Filamento possui pedidos vinculados.');
    }

    $stmt = $db->prepare('DELETE FROM Historico_Estoque WHERE filamento_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt = $db->prepare('DELETE FROM Estoque_Filamento WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
}

function save_model(mysqli $db, array $input): void
{
    $id = (int) ($input['id'] ?? 0);
    $name = trim((string) ($input['nm'] ?? ''));
    $desc = trim((string) ($input['desc'] ?? ''));
    $peso = (float) ($input['peso'] ?? 0);
    $link = trim((string) ($input['link'] ?? ''));
    $image = save_base64_image((string) ($input['foto'] ?? ''), 'modelos');

    if ($name === '') {
        throw new InvalidArgumentException('Nome do modelo e obrigatorio.');
    }

    if ($id > 0) {
        $existing = fetch_model_row($db, $id);
        if (!$existing) {
            throw new InvalidArgumentException('Modelo nao encontrado.');
        }

        $photo = $image ?: ($existing['foto_exemplo'] ?? null);
        $stmt = $db->prepare('UPDATE Galeria_Modelos SET nome_modelo = ?, descricao = ?, peso_estimado = ?, link_stl = ?, foto_exemplo = ? WHERE id = ?');
        $stmt->bind_param('ssdssi', $name, $desc, $peso, $link, $photo, $id);
        $stmt->execute();
        return;
    }

    $stmt = $db->prepare('INSERT INTO Galeria_Modelos (nome_modelo, descricao, peso_estimado, link_stl, foto_exemplo) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssdss', $name, $desc, $peso, $link, $image);
    $stmt->execute();
}

function delete_model(mysqli $db, int $id): void
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Modelo invalido.');
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM Pedido WHERE modelo_id = ? AND status <> 'Cancelado'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    if ($total > 0) {
        throw new InvalidArgumentException('Modelo possui pedidos vinculados.');
    }

    $stmt = $db->prepare('DELETE FROM Galeria_Modelos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
}

function create_order(mysqli $db, array $authUser, array $input): void
{
    $solicitanteId = (int) ($input['solicitanteId'] ?? 0);
    $modeloId = isset($input['mId']) && $input['mId'] !== null ? (int) $input['mId'] : null;
    $filamentoId = (int) ($input['fId'] ?? 0);
    $finalidade = trim((string) ($input['fin'] ?? ''));
    $dataLimite = trim((string) ($input['dataLimite'] ?? '')) ?: null;
    $obs = trim((string) ($input['obs'] ?? ''));
    $img = save_base64_image((string) ($input['img'] ?? ''), 'referencias');

    if ($solicitanteId <= 0 || $filamentoId <= 0 || $finalidade === '') {
        throw new InvalidArgumentException('Dados do pedido invalidos.');
    }

    if (!user_is_admin($authUser) && $solicitanteId !== $authUser['id']) {
        throw http_error('Voce nao pode criar pedidos em nome de outro usuario.', 403);
    }

    $solicitante = fetch_user_scope_row($db, $solicitanteId);
    if (!$solicitante) {
        throw new InvalidArgumentException('Solicitante nao encontrado.');
    }
    assert_same_unit_or_global($authUser, (int) $solicitante['unit_id'], 'Voce nao pode criar pedidos para outra unidade.');

    validate_scoped_filament($db, $filamentoId, $authUser);

    $stmt = $db->prepare('SELECT st.nivel_prioridade FROM Solicitante s LEFT JOIN Setor st ON st.id = s.setor_id WHERE s.id = ?');
    $stmt->bind_param('i', $solicitanteId);
    $stmt->execute();
    $priority = (int) (($stmt->get_result()->fetch_assoc()['nivel_prioridade'] ?? 1));

    $pesoFinal = null;
    if ($modeloId !== null) {
        $stmt = $db->prepare('SELECT peso_estimado FROM Galeria_Modelos WHERE id = ?');
        $stmt->bind_param('i', $modeloId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $pesoFinal = $row ? (float) $row['peso_estimado'] : null;
    }

    $status = 'Pendente';
    $unitId = (int) $solicitante['unit_id'];
    if ($modeloId === null) {
        $stmt = $db->prepare(
            'INSERT INTO Pedido (solicitante_id, modelo_id, filamento_id, finalidade, data_limite, prioridade_bandeira, peso_final_gasto, status, observacoes, imagem_referencia, unit_id) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iissidsssi',
            $solicitanteId,
            $filamentoId,
            $finalidade,
            $dataLimite,
            $priority,
            $pesoFinal,
            $status,
            $obs,
            $img,
            $unitId
        );
    } else {
        $stmt = $db->prepare(
            'INSERT INTO Pedido (solicitante_id, modelo_id, filamento_id, finalidade, data_limite, prioridade_bandeira, peso_final_gasto, status, observacoes, imagem_referencia, unit_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iiissidsssi',
            $solicitanteId,
            $modeloId,
            $filamentoId,
            $finalidade,
            $dataLimite,
            $priority,
            $pesoFinal,
            $status,
            $obs,
            $img,
            $unitId
        );
    }
    $stmt->execute();
}

function update_order_status(mysqli $db, array $input): void
{
    $authUser = require_admin($db);
    $id = (int) ($input['id'] ?? 0);
    $newStatus = normalize_status((string) ($input['status'] ?? ''));
    $inputWeight = isset($input['pesoGasto']) && $input['pesoGasto'] !== null && $input['pesoGasto'] !== ''
        ? round((float) $input['pesoGasto'], 2)
        : null;
    if ($id <= 0 || $newStatus === '') {
        throw new InvalidArgumentException('Pedido ou status invalidos.');
    }

    $allowed = ['Pendente', 'Em Producao', 'Concluido', 'Retirado', 'Cancelado'];
    if (!in_array($newStatus, $allowed, true)) {
        throw new InvalidArgumentException('Status invalido.');
    }

    $db->begin_transaction();
    try {
        $stmt = scoped_prepare($db, 'SELECT id, filamento_id, modelo_id, status, peso_final_gasto, unit_id FROM Pedido WHERE id = ?' . scope_where_clause('unit_id', $authUser, true) . ' FOR UPDATE', 'unit_id', $authUser, 'i', [$id]);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        if (!$order) {
            throw new InvalidArgumentException('Pedido nao encontrado.');
        }

        $oldStatus = normalize_status($order['status']);
        if ($oldStatus === $newStatus) {
            $db->commit();
            return;
        }

        $existingWeight = $order['peso_final_gasto'] !== null ? (float) $order['peso_final_gasto'] : null;
        $weight = $inputWeight;
        if ($order['modelo_id'] !== null) {
            $stmt = $db->prepare('SELECT peso_estimado FROM Galeria_Modelos WHERE id = ?');
            $modelId = (int) $order['modelo_id'];
            $stmt->bind_param('i', $modelId);
            $stmt->execute();
            $estimatedWeight = (float) (($stmt->get_result()->fetch_assoc()['peso_estimado'] ?? 0));
            if ($weight === null && $existingWeight === null && $estimatedWeight > 0) {
                $weight = $estimatedWeight;
            }
        }

        if ($weight === null) {
            $weight = $existingWeight;
        }

        $consumesStockStatuses = ['Concluido', 'Retirado'];
        $oldConsumesStock = in_array($oldStatus, $consumesStockStatuses, true);
        $newConsumesStock = in_array($newStatus, $consumesStockStatuses, true);

        if ($newConsumesStock && ($weight === null || $weight <= 0)) {
            throw new InvalidArgumentException('Informe a quantidade gasta para finalizar o pedido.');
        }

        if (!$oldConsumesStock && $newConsumesStock && $weight > 0) {
            change_filament_stock($db, (int) $order['filamento_id'], -$weight);
            insert_history($db, (int) $order['filamento_id'], $weight, 'Saida', (int) $order['unit_id']);
        }

        if ($oldConsumesStock && !$newConsumesStock && $weight > 0) {
            change_filament_stock($db, (int) $order['filamento_id'], $weight);
            insert_history($db, (int) $order['filamento_id'], $weight, 'Entrada', (int) $order['unit_id']);
        }

        $stmt = $db->prepare('UPDATE Pedido SET status = ?, peso_final_gasto = ? WHERE id = ?');
        $stmt->bind_param('sdi', $newStatus, $weight, $id);
        $stmt->execute();

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

function create_user(mysqli $db, array $input): array
{
    ensure_auth_columns($db);
    $authUser = require_admin($db);

    $nome = trim((string) ($input['nome'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $usuario = trim((string) ($input['usuario'] ?? ''));
    $matricula = preg_replace('/\D+/', '', (string) ($input['matricula'] ?? ''));
    $setorId = (int) ($input['setorId'] ?? 0);
    $tipo = (($input['tipo'] ?? 'comum') === 'admin') ? 'admin' : 'comum';
    $perfil = normalize_access_profile((string) ($input['perfil'] ?? ''), $tipo);
    $unitId = isset($input['unitId']) ? (int) $input['unitId'] : require_user_unit_id($authUser);

    if ($nome === '' || $email === '' || $usuario === '' || $matricula === '' || $setorId <= 0) {
        throw new InvalidArgumentException('Preencha nome, email, usuario, matricula e setor.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Informe um email valido.');
    }

    if (!user_can_manage_unit($authUser, $unitId)) {
        throw http_error('Voce nao pode cadastrar usuarios para outra unidade.', 403);
    }

    $stmt = $db->prepare('SELECT id FROM Setor WHERE id = ?');
    $stmt->bind_param('i', $setorId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('Setor invalido.');
    }

    $stmt = $db->prepare('SELECT id FROM Solicitante WHERE email = ? OR usuario = ? OR matricula = ? LIMIT 1');
    $stmt->bind_param('sss', $email, $usuario, $matricula);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('Ja existe um usuario com este email, usuario ou matricula.');
    }

    $passwordPreview = default_password($nome, $matricula);
    $passwordHash = password_hash($passwordPreview, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Nao foi possivel gerar a senha do usuario.');
    }

    $stmt = $db->prepare(
        'INSERT INTO Solicitante (nome, email, setor_id, total_gasto_acumulado, tipo_usuario, escopo_acesso, foto_perfil, usuario, matricula, senha_hash, unit_id)
         VALUES (?, ?, ?, 0.00, ?, ?, NULL, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssisssssi', $nome, $email, $setorId, $tipo, $perfil, $usuario, $matricula, $passwordHash, $unitId);
    $stmt->execute();

    return [
        'id' => (int) $db->insert_id,
        'usuario' => $usuario,
        'senhaPadrao' => $passwordPreview,
        'regraSenha' => 'matricula',
    ];
}

function default_password(string $nome, string $matricula): string
{
    return $matricula;
}

function update_user(mysqli $db, array $input): void
{
    $authUser = require_admin($db);
    $id = (int) ($input['id'] ?? 0);
    $nome = trim((string) ($input['nome'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $usuario = trim((string) ($input['usuario'] ?? ''));
    $matricula = preg_replace('/\D+/', '', (string) ($input['matricula'] ?? ''));
    $setorId = (int) ($input['setorId'] ?? 0);
    $tipo = (($input['tipo'] ?? 'comum') === 'admin') ? 'admin' : 'comum';
    $perfil = normalize_access_profile((string) ($input['perfil'] ?? ''), $tipo);
    $unitId = isset($input['unitId']) ? (int) $input['unitId'] : require_user_unit_id($authUser);

    if ($id <= 0 || $nome === '' || $email === '' || $usuario === '' || $matricula === '' || $setorId <= 0) {
        throw new InvalidArgumentException('Preencha os dados do usuario corretamente.');
    }

    if (!user_can_manage_unit($authUser, $unitId)) {
        throw http_error('Voce nao pode alterar usuarios de outra unidade.', 403);
    }

    $stmt = scoped_prepare($db, 'SELECT id FROM Solicitante WHERE id = ?' . scope_where_clause('unit_id', $authUser, true), 'unit_id', $authUser, 'i', [$id]);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('Usuario nao encontrado.');
    }

    $stmt = $db->prepare('SELECT id FROM Solicitante WHERE (email = ? OR usuario = ? OR matricula = ?) AND id <> ? LIMIT 1');
    $stmt->bind_param('sssi', $email, $usuario, $matricula, $id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('Ja existe outro usuario com este email, usuario ou matricula.');
    }

    $stmt = $db->prepare('UPDATE Solicitante SET nome = ?, email = ?, usuario = ?, matricula = ?, setor_id = ?, tipo_usuario = ?, escopo_acesso = ?, unit_id = ? WHERE id = ?');
    $stmt->bind_param('sssisssii', $nome, $email, $usuario, $matricula, $setorId, $tipo, $perfil, $unitId, $id);
    $stmt->execute();
}

function inactivate_user(mysqli $db, int $id, array $admin): void
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Usuario invalido.');
    }
    if ($id === (int) $admin['id']) {
        throw new InvalidArgumentException('Voce nao pode inativar o proprio usuario.');
    }

    $stmt = scoped_prepare($db, 'UPDATE Solicitante SET ativo = 0 WHERE id = ?' . scope_where_clause('unit_id', $admin, true), 'unit_id', $admin, 'i', [$id]);
    $stmt->execute();
}

function normalize_identifier(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '';
    return $value;
}

function change_filament_stock(mysqli $db, int $filamentId, float $delta): void
{
    $stmt = $db->prepare('SELECT peso_atual_gramas FROM Estoque_Filamento WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $filamentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        throw new InvalidArgumentException('Filamento nao encontrado.');
    }

    $newWeight = round((float) $row['peso_atual_gramas'] + $delta, 2);
    if ($newWeight < 0) {
        throw new InvalidArgumentException('Filamento insuficiente para concluir este pedido.');
    }

    $alert = $newWeight <= 200 ? 1 : 0;
    $stmt = $db->prepare('UPDATE Estoque_Filamento SET peso_atual_gramas = ?, status_alerta = ? WHERE id = ?');
    $stmt->bind_param('dii', $newWeight, $alert, $filamentId);
    $stmt->execute();
}

function insert_history(mysqli $db, int $filamentId, float $amount, string $type, int $unitId): void
{
    $stmt = $db->prepare('INSERT INTO Historico_Estoque (filamento_id, quantidade_movimentada, tipo_movimentacao, unit_id) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('idsi', $filamentId, $amount, $type, $unitId);
    $stmt->execute();
}

function fetch_model_row(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM Galeria_Modelos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}

function table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}

function ensure_auth_columns(mysqli $db): void
{
    $required = ['usuario', 'matricula', 'senha_hash', 'unit_id', 'escopo_acesso'];
    foreach ($required as $column) {
        if (!column_exists($db, 'Solicitante', $column)) {
            throw new RuntimeException(
                'Execute a migracao de autenticacao e multi-unidades antes de usar login e cadastro de usuarios.',
                500
            );
        }
    }
}

function save_user_photo(mysqli $db, array $authUser, array $input): void
{
    $id = (int) ($input['id'] ?? 0);
    $photo = save_base64_image((string) ($input['foto'] ?? ''), 'perfis');

    if ($id <= 0 || $photo === null) {
        throw new InvalidArgumentException('Dados da foto de perfil invalidos.');
    }

    if (!user_is_admin($authUser) && $id !== $authUser['id']) {
        throw http_error('Voce nao pode alterar a foto de outro usuario.', 403);
    }

    if (user_is_admin($authUser) && $id !== (int) $authUser['id']) {
        require_scoped_record($db, 'Solicitante', $id, $authUser);
    }

    if (!column_exists($db, 'Solicitante', 'foto_perfil')) {
        throw new InvalidArgumentException('Execute o SQL de alteracao da foto de perfil antes de usar este recurso.');
    }

    $stmt = $db->prepare('UPDATE Solicitante SET foto_perfil = ? WHERE id = ?');
    $stmt->bind_param('si', $photo, $id);
    $stmt->execute();

    if ($stmt->affected_rows < 0) {
        throw new RuntimeException('Nao foi possivel salvar a foto de perfil.');
    }
}

function normalize_access_profile(string $profile, string $tipoUsuario): string
{
    $profile = trim($profile);
    if (in_array($profile, ['admin_local', 'admin_nacional', 'colaborador'], true)) {
        return $profile;
    }

    return $tipoUsuario === 'admin' ? 'admin_local' : 'colaborador';
}

function user_is_admin(array $user): bool
{
    return in_array($user['perfil'] ?? null, ['admin_local', 'admin_nacional'], true);
}

function user_has_global_scope(array $user): bool
{
    return ($user['perfil'] ?? null) === 'admin_nacional';
}

function require_user_unit_id(array $user): int
{
    $unitId = (int) ($user['unitId'] ?? 0);
    if ($unitId <= 0) {
        throw http_error('Usuario sem unidade vinculada.', 403);
    }

    return $unitId;
}

function scope_where_clause(string $column, array $authUser, bool $hasWhere = false): string
{
    if (user_has_global_scope($authUser)) {
        return '';
    }

    return ($hasWhere ? ' AND ' : ' WHERE ') . $column . ' = ?';
}

function scoped_prepare(
    mysqli $db,
    string $sql,
    string $unitColumn,
    array $authUser,
    string $types = '',
    array $params = []
): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if (!user_has_global_scope($authUser)) {
        $types .= 'i';
        $params[] = require_user_unit_id($authUser);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    return $stmt;
}

function user_can_manage_unit(array $authUser, int $targetUnitId): bool
{
    if ($targetUnitId <= 0) {
        return false;
    }

    return user_has_global_scope($authUser) || require_user_unit_id($authUser) === $targetUnitId;
}

function fetch_user_scope_row(mysqli $db, int $userId): ?array
{
    $stmt = $db->prepare('SELECT id, unit_id FROM Solicitante WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function assert_same_unit_or_global(array $authUser, int $targetUnitId, string $message): void
{
    if (!user_can_manage_unit($authUser, $targetUnitId)) {
        throw http_error($message, 403);
    }
}

function validate_scoped_filament(mysqli $db, int $filamentId, array $authUser): void
{
    $stmt = scoped_prepare(
        $db,
        'SELECT id FROM Estoque_Filamento WHERE id = ?' . scope_where_clause('unit_id', $authUser, true),
        'unit_id',
        $authUser,
        'i',
        [$filamentId]
    );
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw http_error('Filamento nao encontrado para a unidade do usuario.', 403);
    }
}

function require_scoped_record(mysqli $db, string $table, int $id, array $authUser): array
{
    $stmt = scoped_prepare(
        $db,
        "SELECT * FROM {$table} WHERE id = ?" . scope_where_clause('unit_id', $authUser, true),
        'unit_id',
        $authUser,
        'i',
        [$id]
    );
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        throw http_error('Registro nao encontrado para esta unidade.', 404);
    }

    return $row;
}

function save_base64_image(string $value, string $folder): ?string
{
    $value = trim($value);
    if ($value === '' || !str_starts_with($value, 'data:image/')) {
        return null;
    }

    if (!preg_match('#^data:image/(\w+);base64,(.+)$#', $value, $matches)) {
        throw new InvalidArgumentException('Imagem enviada em formato invalido.');
    }

    $extension = strtolower($matches[1]);
    $extension = in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? $extension : 'png';
    $binary = base64_decode($matches[2], true);
    if ($binary === false) {
        throw new InvalidArgumentException('Nao foi possivel processar a imagem.');
    }

    $dir = __DIR__ . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de uploads.');
    }

    $name = uniqid($folder . '_', true) . '.' . $extension;
    $path = $dir . '/' . $name;
    file_put_contents($path, $binary);

    return 'uploads/' . $folder . '/' . $name;
}

function normalize_status(string $status): string
{
    $map = [
        'Em Produção' => 'Em Producao',
        'Em Producao' => 'Em Producao',
        'Concluído' => 'Concluido',
        'Concluido' => 'Concluido',
    ];

    return $map[$status] ?? $status;
}

function http_error(string $message, int $status): RuntimeException
{
    return new RuntimeException($message, $status);
}

function get_authorization_header(): string
{
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
    ];

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $candidates[] = (string) $value;
            }
        }
    }

    foreach ($candidates as $candidate) {
        if (trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return '';
}
