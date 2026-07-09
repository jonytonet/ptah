---
description: Implementa o plano mais recente do ptah com o analista-sistemas
argument-hint: [ajustes ou plano, opcional]
disable-model-invocation: true
---
Use o subagente analista-sistemas para implementar o plano técnico mais recente desta conversa, passando o plano NA ÍNTEGRA no prompt do subagente, junto com estes ajustes (se houver): $ARGUMENTS

Se não existir plano na conversa, NÃO implemente nada — peça para eu rodar /planejar primeiro.
Lembre o subagente: regra absoluta de banco vale (nenhuma migration/seed/DML/DDL executada; apenas preparar artefatos); rodar `vendor/bin/phpunit` + `vendor/bin/phpstan` + `vendor/bin/pint` no pacote; nada de commit/push/merge sem minha autorização.
Ao final, apresente o entregável completo do subagente, incluindo a seção "Pendente de execução humana".
