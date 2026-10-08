<?php
/*
 * Cadastro de Tutores — donos dos animais (tabela futura: tutores)
 * Lógica adicional: valida o CPF pelos dígitos verificadores, calcula a idade,
 * recusa menores de 18 anos (responsável legal pelo animal) e classifica o
 * tutor pela faixa etária.
 */

declare(strict_types=1);
require __DIR__ . '/validacao.php';

exigirPost();
$v = new Validador();

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR',
        'PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

if ($v->obrigatorio('nome', 'Nome')) {
    $v->tamanho('nome', 'Nome', 3, 100);
    if (!preg_match('/^[\p{L} \'.-]+$/u', campo('nome'))) {
        $v->erro('nome', 'O nome deve conter apenas letras.');
    } elseif (!str_contains(campo('nome'), ' ')) {
        $v->erro('nome', 'Informe nome e sobrenome.');
    }
}

if ($v->obrigatorio('cpf', 'CPF') && !cpfValido(campo('cpf'))) {
    $v->erro('cpf', 'CPF inválido (dígitos verificadores não conferem).');
}

$nascimento = null;
if ($v->obrigatorio('data_nascimento', 'Data de nascimento')) {
    $nascimento = $v->data('data_nascimento', 'Data de nascimento');
}

if ($v->obrigatorio('email', 'E-mail')) {
    $v->email('email');
}

$telefone = apenasDigitos(campo('telefone'));
if ($v->obrigatorio('telefone', 'Telefone') && (strlen($telefone) < 10 || strlen($telefone) > 11)) {
    $v->erro('telefone', 'Telefone deve ter DDD + número (10 ou 11 dígitos).');
}

if ($v->obrigatorio('cidade', 'Cidade')) {
    $v->tamanho('cidade', 'Cidade', 2, 60);
}

if ($v->obrigatorio('uf', 'UF')) {
    $v->opcao('uf', 'UF', $ufs);
}

// ----- Lógica adicional: idade -----
$idade = 0;
if ($nascimento !== null) {
    $hoje = new DateTimeImmutable('today');
    if ($nascimento > $hoje) {
        $v->erro('data_nascimento', 'A data de nascimento não pode estar no futuro.');
    } else {
        $idade = $nascimento->diff($hoje)->y;
        if ($idade < 18) {
            $v->erro('data_nascimento', "O tutor tem {$idade} anos. É preciso ter 18 anos ou mais para ser tutor responsável.");
        } elseif ($idade > 120) {
            $v->erro('data_nascimento', 'Data de nascimento improvável. Confira o ano.');
        }
    }
}

$v->encerrarSeHouverErros();

if ($idade >= 60) {
    $perfil = 'Tutor 60+ (atendimento prioritário)';
} elseif ($idade <= 25) {
    $perfil = 'Tutor jovem (até 25 anos)';
} else {
    $perfil = 'Tutor adulto';
}

$fone = strlen($telefone) === 11
    ? sprintf('(%s) %s-%s', substr($telefone, 0, 2), substr($telefone, 2, 5), substr($telefone, 7))
    : sprintf('(%s) %s-%s', substr($telefone, 0, 2), substr($telefone, 2, 4), substr($telefone, 6));

$alertas = [];
if (strlen($telefone) === 10) {
    $alertas[] = 'O telefone informado é fixo; peça um celular para avisar pelo WhatsApp quando o pet estiver pronto.';
}

responder(200, [
    'ok' => true,
    'mensagem' => 'Tutor validado com sucesso! (o salvamento no banco será feito na próxima etapa)',
    'resumo' => [
        'Nome' => mb_convert_case(campo('nome'), MB_CASE_TITLE, 'UTF-8'),
        'CPF' => formatarCpf(campo('cpf')),
        'Idade' => $idade . ' anos',
        'Perfil' => $perfil,
        'Telefone' => $fone,
        'Cidade/UF' => campo('cidade') . ' / ' . campo('uf'),
    ],
    'alertas' => $alertas,
]);
