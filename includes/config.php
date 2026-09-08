<?php
/**
 * PRYSMA - Configuração central: conexão com o banco, sessão e helpers.
 * Ajuste as constantes de conexão abaixo conforme o ambiente local.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // não expor erros na tela em produção/demo
ini_set('log_errors', '1');

// ---------------------------------------------------------------------
// Conexão com o banco (ajuste usuário/senha/host se necessário)
// ---------------------------------------------------------------------
const DB_HOST = '127.0.0.1';
const DB_PORT = '5432';
const DB_NAME = 'prysma';
const DB_USER = 'prysma_app';
const DB_PASS = 'prysma_app';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ---------------------------------------------------------------------
// Sessão
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------
// Helpers gerais
// ---------------------------------------------------------------------

/** Escapa texto para saída segura em HTML. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Trunca um texto UTF-8 em até $largura "caracteres" visuais, acrescentando
 * $fim quando corta. Equivalente a mb_strimwidth(), mas sem depender da
 * extensão mbstring (que pode estar desabilitada no php.ini) -- usa o
 * suporte a Unicode nativo do PCRE (modificador /u), presente em qualquer
 * PHP padrão.
 */
function strim_utf8(?string $texto, int $largura, string $fim = '…'): string
{
    $texto = $texto ?? '';
    if (preg_match('/^.{0,' . $largura . '}/us', $texto, $m) !== 1 || $m[0] === $texto) {
        return $texto;
    }
    return $m[0] . $fim;
}

/** Formata valor monetário em Real. */
function money(float|string|null $value): string
{
    $v = (float) ($value ?? 0);
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/** Formata data (Y-m-d ou datetime) para dd/mm/aaaa. */
function fdate(?string $value): string
{
    if (!$value) {
        return '-';
    }
    try {
        $dt = new DateTime($value);
        return $dt->format('d/m/Y');
    } catch (Exception) {
        return h($value);
    }
}

/** Formata datetime para dd/mm/aaaa HH:ii. */
function fdatetime(?string $value): string
{
    if (!$value) {
        return '-';
    }
    try {
        $dt = new DateTime($value);
        return $dt->format('d/m/Y H:i');
    } catch (Exception) {
        return h($value);
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Mensagem "flash" (uma exibição só) via sessão. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Token simples anti-CSRF por sessão. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('Sessão expirada. Volte e tente novamente.');
    }
}

// ---------------------------------------------------------------------
// Autenticação / controle de acesso
// ---------------------------------------------------------------------

/** Retorna os dados do usuário autenticado (array) ou null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Exige sessão ativa e, opcionalmente, um perfil específico
 * ('locador', 'locatario' ou 'admin'). Redireciona ao login caso contrário.
 */
function require_login(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login.php');
    }
    if ($role !== null && $user['perfil'] !== $role) {
        http_response_code(403);
        exit('Acesso não permitido para este perfil.');
    }
    return $user;
}

function base_url_for_role(string $role): string
{
    return match ($role) {
        'locador' => '/locador/dashboard.php',
        'locatario' => '/locatario/dashboard.php',
        'admin' => '/admin/dashboard.php',
        default => '/login.php',
    };
}
