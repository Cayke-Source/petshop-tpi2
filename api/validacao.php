<?php
/*
 * PetVida — funções compartilhadas pelos scripts da pasta api/.
 *
 * Os scripts PHP só devolvem JSON (nenhum HTML é misturado com PHP).
 * Neste momento não há banco de dados: o servidor recebe a requisição,
 * valida os campos e aplica uma pequena lógica de negócio.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/** Envia a resposta JSON e encerra o script. */
function responder(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Só aceita requisições POST (os formulários enviam via fetch com POST). */
function exigirPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        responder(405, ['ok' => false, 'mensagem' => 'Método não permitido. Envie os dados via POST.']);
    }
}

/** Lê um campo de $_POST já sem espaços nas pontas. */
function campo(string $nome): string
{
    return trim((string)($_POST[$nome] ?? ''));
}

/** Converte "1.234,56" ou "1234.56" em float. Retorna null se não for número. */
function paraNumero(string $valor): ?float
{
    $valor = str_replace(' ', '', $valor);
    if (str_contains($valor, ',')) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }
    return is_numeric($valor) ? (float)$valor : null;
}

function apenasDigitos(string $valor): string
{
    return preg_replace('/\D/', '', $valor) ?? '';
}

function dinheiro(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/** Cria um DateTimeImmutable a partir de "AAAA-MM-DD" (formato do input date). */
function paraData(string $valor): ?DateTimeImmutable
{
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    if ($data === false || $data->format('Y-m-d') !== $valor) {
        return null;
    }
    return $data;
}

/** Valida CPF pelos dígitos verificadores. */
function cpfValido(string $cpf): bool
{
    $cpf = apenasDigitos($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int)$cpf[$t] !== $digito) {
            return false;
        }
    }
    return true;
}

/** Valida CNPJ pelos dígitos verificadores. */
function cnpjValido(string $cnpj): bool
{
    $cnpj = apenasDigitos($cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }
    $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    foreach ([$pesos1, $pesos2] as $indice => $pesos) {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (int)$cnpj[$i] * $peso;
        }
        $resto = $soma % 11;
        $digito = $resto < 2 ? 0 : 11 - $resto;
        if ((int)$cnpj[12 + $indice] !== $digito) {
            return false;
        }
    }
    return true;
}

function formatarCpf(string $cpf): string
{
    $d = apenasDigitos($cpf);
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

function formatarCnpj(string $cnpj): string
{
    $d = apenasDigitos($cnpj);
    return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
}

/**
 * Validador simples: acumula as mensagens de erro por nome de campo.
 * Cada método só registra o primeiro erro de cada campo.
 */
class Validador
{
    private array $erros = [];

    public function erro(string $campo, string $mensagem): void
    {
        if (!isset($this->erros[$campo])) {
            $this->erros[$campo] = $mensagem;
        }
    }

    public function temErro(string $campo): bool
    {
        return isset($this->erros[$campo]);
    }

    public function obrigatorio(string $campo, string $rotulo): bool
    {
        if (campo($campo) === '') {
            $this->erro($campo, "O campo {$rotulo} é obrigatório.");
            return false;
        }
        return true;
    }

    public function tamanho(string $campo, string $rotulo, int $min, int $max): void
    {
        $tam = mb_strlen(campo($campo));
        if ($tam < $min || $tam > $max) {
            $this->erro($campo, "{$rotulo} deve ter entre {$min} e {$max} caracteres.");
        }
    }

    public function email(string $campo): void
    {
        if (!filter_var(campo($campo), FILTER_VALIDATE_EMAIL)) {
            $this->erro($campo, 'Informe um e-mail válido.');
        }
    }

    /** Valida número dentro de uma faixa e devolve o valor convertido. */
    public function numero(string $campo, string $rotulo, float $min, float $max): ?float
    {
        $n = paraNumero(campo($campo));
        if ($n === null) {
            $this->erro($campo, "{$rotulo} deve ser um número.");
            return null;
        }
        if ($n < $min || $n > $max) {
            $this->erro($campo, "{$rotulo} deve estar entre " . $this->fmt($min) . ' e ' . $this->fmt($max) . '.');
            return null;
        }
        return $n;
    }

    public function inteiro(string $campo, string $rotulo, int $min, int $max): ?int
    {
        $v = campo($campo);
        if (!preg_match('/^-?\d+$/', $v)) {
            $this->erro($campo, "{$rotulo} deve ser um número inteiro.");
            return null;
        }
        $n = (int)$v;
        if ($n < $min || $n > $max) {
            $this->erro($campo, "{$rotulo} deve estar entre {$min} e {$max}.");
            return null;
        }
        return $n;
    }

    public function data(string $campo, string $rotulo): ?DateTimeImmutable
    {
        $d = paraData(campo($campo));
        if ($d === null) {
            $this->erro($campo, "{$rotulo} deve ser uma data válida.");
        }
        return $d;
    }

    public function opcao(string $campo, string $rotulo, array $permitidas): void
    {
        if (!in_array(campo($campo), $permitidas, true)) {
            $this->erro($campo, "Selecione uma opção válida para {$rotulo}.");
        }
    }

    /** Se houver erros, responde 422 e encerra. */
    public function encerrarSeHouverErros(): void
    {
        if ($this->erros !== []) {
            responder(422, [
                'ok' => false,
                'mensagem' => 'Existem ' . count($this->erros) . ' campo(s) com problema. Corrija e envie novamente.',
                'erros' => $this->erros,
            ]);
        }
    }

    private function fmt(float $n): string
    {
        return floor($n) == $n ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.');
    }
}
