---
description: Revisão do ptah com nota (backend, frontend ou ambos, conforme o diff)
argument-hint: [escopo opcional: backend | frontend | ambos]
disable-model-invocation: true
---
Escopo solicitado: $ARGUMENTS (se vazio, determine pelo `git diff`: Service/Repository/DTO/Livewire server-side/comandos/migrations → backend; views Blade/componentes Livewire-Alpine/forge/CSS/Tailwind → frontend; ambos se misto).

Invoque o subagente revisor-backend e/ou revisor-frontend conforme o escopo, passando no prompt o contexto da tarefa implementada.

Apresente os relatórios COMPLETOS com as notas. Não corrija nada, não resuma achados, não invoque o analista-sistemas.
