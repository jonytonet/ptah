---
description: Ciclo completo plano → implementação → revisão no ptah, em loop até os revisores aplicáveis darem 10/10
argument-hint: [descrição da tarefa]
disable-model-invocation: true
---
Orquestre o ciclo completo de desenvolvimento para a tarefa:

$ARGUMENTS

FASE 1 — PLANO
1. Invoque o subagente engenheiro-software com a tarefa.
2. Se o plano contiver perguntas críticas em aberto, PARE e apresente as perguntas. Só prossiga após minhas respostas.
3. Registre o escopo de revisão declarado no plano (backend, frontend ou ambos).

FASE 2 — IMPLEMENTAÇÃO
4. Invoque o subagente analista-sistemas passando o plano NA ÍNTEGRA.

FASE 3 — REVISÃO
5. Invoque o(s) revisor(es) do escopo: revisor-backend para servidor/Service/Repository/DTO/Livewire server-side; revisor-frontend para Blade/Livewire/Alpine/Tailwind/forge. Ambos se misto.
6. Apresente as notas e os achados na íntegra.

FASE 4 — DECISÃO
7. Se TODAS as notas aplicáveis forem 10/10: encerre com o relatório final.
8. Caso contrário, ciclo de correção:
   - Achado 🔴 de natureza arquitetural → invoque engenheiro-software para revisar o plano ANTES de corrigir.
   - Demais achados → invoque analista-sistemas passando SOMENTE os achados dos revisores como plano de correção. Nada além do apontado pode ser alterado.
   - Volte à FASE 3 (re-revisão completa, não incremental).

REGRAS DO LOOP (invioláveis):
- Máximo de 5 ciclos de correção. Ao atingir o limite sem 10/10, PARE e apresente: notas finais, achados remanescentes e recomendação para decisão humana. NUNCA afrouxe critérios para forçar convergência e NUNCA peça aos revisores para reconsiderar a nota.
- REGRA ABSOLUTA DE BANCO em todos os ciclos e agentes: nenhuma migration, seed, DML ou DDL é executada, em nenhuma hipótese. Artefatos de banco ficam apenas preparados, listados em "Pendente de execução humana".
- Testes: no pacote `ptah/`, `vendor/bin/phpunit` é seguro (Testbench + sqlite `:memory:`); mais `vendor/bin/phpstan analyse` e `vendor/bin/pint`. No app `petplace/`, `php artisan test` só após confirmar conexão de teste dedicada. Se não executáveis, revisores avaliam estaticamente — isso não impede o 10.
- Nenhum commit, push ou merge sem instrução explícita minha.
- Se qualquer subagente sinalizar bloqueio ou pergunta crítica, PARE e me consulte.

RELATÓRIO FINAL (sempre, convergindo ou não):
- Notas por revisor e nº de ciclos usados.
- Arquivos criados/alterados (`git diff --stat`).
- Pendências de execução humana (comandos de banco, ambiente, reversibilidade, rollback).
- Desvios e decisões tomadas no caminho.
