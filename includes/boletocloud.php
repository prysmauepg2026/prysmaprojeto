<?php
/**
 * PRYSMA - Integração com o Sistema Bancário (ator secundário do UC03).
 *
 * Implementada usando o ambiente SANDBOX da Boleto Cloud (developers.boleto.cloud),
 * que emite boletos de teste e retorna o PDF pronto diretamente na resposta da
 * API (diferente do Mercado Pago, aqui não depende de um link externo que possa
 * ficar indisponível depois).
 *
 * Para funcionar, é preciso:
 * 1. Criar uma conta gratuita em https://sandbox.boletocloud.com
 * 2. Gerar sua API Key (menu da conta) e colocar em BC_API_KEY abaixo.
 * 3. Cadastrar uma conta bancária de teste (menu "Conta" -> "Nova conta") e
 *    gerar o token dela (Editar dados -> Gerar Token), colocando em BC_CONTA_TOKEN.
 * (ver README.md, seção "Configurar o Sistema Bancário", para o passo a passo).
 */

declare(strict_types=1);

const BC_API_KEY = 'api-key_GbzECHFMab6w5sLpO3yr5lWiYWqCHNX65-K0P4YCzqI=';       // ex.: api-key_AbCdEf123...
const BC_CONTA_TOKEN = 'api-key_-9fNTppFo7LQ3aj2dNHuyk8vHjhK9N8KmW2yEpvM5aI=';   // token gerado na tela da conta bancária
const BC_API_BASE = 'https://sandbox.boletocloud.com/api/v1';

/** Pasta onde os PDFs recebidos da Boleto Cloud são salvos localmente. */
const BC_STORAGE_DIR = __DIR__ . '/../storage/boletos';

/**
 * Exceção usada para o fluxo alternativo 4a do UC03
 * ("Falha na comunicação com o Sistema Bancário").
 */
class SistemaBancarioIndisponivelException extends RuntimeException
{
}

/**
 * Solicita ao Sistema Bancário (Boleto Cloud sandbox) a emissão de um boleto
 * para a cobrança informada. Salva o PDF retornado em disco (dentro da própria
 * aplicação) e retorna os dados relevantes para exibir/baixar a 2ª via, ou
 * lança SistemaBancarioIndisponivelException em caso de falha (timeout, erro
 * de rede, credenciais ausentes, recusa da API).
 *
 * @param array{
 *   id_cobranca:int, valor:float, vencimento:string, descricao:string,
 *   nome:string, email:string, cpf:string,
 *   cep?:string, logradouro?:string, numero?:string, bairro?:string,
 *   cidade?:string, uf?:string
 * } $dados
 * @return array{id_externo:string, codigo_barras:?string, linha_digitavel:?string, url_pdf:string, arquivo:string}
 */
function bc_gerar_boleto(array $dados): array
{
    if (BC_API_KEY === '' || str_starts_with(BC_API_KEY, 'COLOQUE_AQUI')
        || BC_CONTA_TOKEN === '' || str_starts_with(BC_CONTA_TOKEN, 'COLOQUE_AQUI')) {
        throw new SistemaBancarioIndisponivelException(
            'Credenciais do Sistema Bancário não configuradas (BC_API_KEY / BC_CONTA_TOKEN).'
        );
    }

    $cepLimpo = preg_replace('/\D/', '', $dados['cep'] ?? '84000000') ?: '84000000';
    $cepFormatado = substr($cepLimpo, 0, 5) . '-' . substr($cepLimpo, 5, 3);

    // A API da Boleto Cloud usa notação com PONTO para campos aninhados no
    // corpo form-urlencoded (ex.: "boleto.pagador.nome=..."), e não a
    // notação com colchetes que o http_build_query() do PHP gera por padrão
    // para arrays aninhados ("boleto[pagador][nome]") — por isso montamos o
    // payload já como um array plano com as chaves no formato certo.
    $payload = [
        'boleto.conta.token' => BC_CONTA_TOKEN,
        'boleto.titulo' => 'DM', // Duplicata Mercantil
        'boleto.documento' => 'PRYSMA-COB-' . $dados['id_cobranca'],
        'boleto.emissao' => date('Y-m-d'),
        'boleto.vencimento' => $dados['vencimento'],
        'boleto.valor' => number_format((float) $dados['valor'], 2, '.', ''),
        'boleto.instrucao' => "Aluguel referente a cobranca #{$dados['id_cobranca']} - PRYSMA / CDC Imoveis.",
        'boleto.pagador.nome' => $dados['nome'],
        'boleto.pagador.cprf' => preg_replace('/\D/', '', $dados['cpf']),
        'boleto.pagador.email' => $dados['email'],
        'boleto.pagador.endereco.cep' => $cepFormatado,
        'boleto.pagador.endereco.uf' => $dados['uf'] ?? 'PR',
        'boleto.pagador.endereco.localidade' => $dados['cidade'] ?? 'Ponta Grossa',
        'boleto.pagador.endereco.bairro' => $dados['bairro'] ?? 'Centro',
        'boleto.pagador.endereco.logradouro' => $dados['logradouro'] ?? 'Rua Exemplo',
        'boleto.pagador.endereco.numero' => (string) ($dados['numero'] ?? '1'),
    ];

    $headersRecebidos = [];
    $ch = curl_init(BC_API_BASE . '/boletos');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(BC_API_KEY . ':token'),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/pdf, application/json',
        ],
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADERFUNCTION => function ($curl, string $headerLine) use (&$headersRecebidos) {
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $headersRecebidos[trim(strtolower($parts[0]))] = trim($parts[1]);
            }
            return strlen($headerLine);
        },
    ]);
    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErrno !== 0 || $response === false) {
        error_log('[boletocloud] erro de conexão: ' . $curlError);
        throw new SistemaBancarioIndisponivelException('Falha na comunicação com o Sistema Bancário.');
    }

    if ($httpCode >= 400) {
        error_log('[boletocloud] resposta com erro (HTTP ' . $httpCode . '): ' . substr((string) $response, 0, 2000));
        throw new SistemaBancarioIndisponivelException('O Sistema Bancário recusou a solicitação (HTTP ' . $httpCode . ').');
    }

    $tokenBoleto = $headersRecebidos['x-boletocloud-token'] ?? null;
    if (!$tokenBoleto || $response === '' || $response === null) {
        error_log('[boletocloud] resposta sem token/PDF válido (HTTP ' . $httpCode . ')');
        throw new SistemaBancarioIndisponivelException('O Sistema Bancário retornou uma resposta inesperada.');
    }

    // Salva o PDF localmente; será servido pela própria aplicação
    // (locatario/boleto_pdf.php), sem depender de link externo.
    if (!is_dir(BC_STORAGE_DIR)) {
        mkdir(BC_STORAGE_DIR, 0775, true);
    }
    $nomeArquivo = 'cobranca-' . $dados['id_cobranca'] . '.pdf';
    file_put_contents(BC_STORAGE_DIR . '/' . $nomeArquivo, $response);

    return [
        'id_externo' => $tokenBoleto,
        'codigo_barras' => $headersRecebidos['x-boletocloud-codigo-de-barras'] ?? null,
        'linha_digitavel' => $headersRecebidos['x-boletocloud-linha-digitavel'] ?? null,
        'url_pdf' => '/locatario/boleto_pdf.php?cobranca=' . $dados['id_cobranca'],
        'arquivo' => $nomeArquivo,
    ];
}