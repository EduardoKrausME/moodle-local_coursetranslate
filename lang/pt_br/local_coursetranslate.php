<?php
// Este arquivo faz parte do Moodle - http://moodle.org/

/**
 * Strings em português do Brasil.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Tradutor de curso';
$string['coursetranslate:translate'] = 'Traduzir conteúdo do curso';
$string['starttranslation'] = 'Criar trabalho de tradução';
$string['sourcelang'] = 'Idioma de origem';
$string['targetlang'] = 'Idioma de destino';
$string['translatefullname'] = 'Incluir nome completo do curso';
$string['translatesummary'] = 'Incluir resumo do curso';
$string['includeglossary'] = 'Incluir entradas de glossário gerenciadas por professores';
$string['terminology'] = 'Terminologia obrigatória';
$string['terminology_help'] = 'Uma regra por linha no formato Origem = Destino. Esses termos são protegidos como tokens para que o termo de destino seja restaurado exatamente.';
$string['jobcreated'] = 'Trabalho de tradução criado com {$a} campos.';
$string['job'] = 'Trabalho de tradução';
$string['source'] = 'Original';
$string['translation'] = 'Tradução';
$string['status'] = 'Status';
$string['field'] = 'Campo';
$string['select'] = 'Selecionar';
$string['translatepending'] = 'Traduzir campos pendentes';
$string['translateselected'] = 'Traduzir selecionados';
$string['applyselected'] = 'Aplicar selecionados no curso original';
$string['createcopy'] = 'Criar cópia traduzida do curso';
$string['confirmapply'] = 'Confirmar aplicação das traduções';
$string['confirmapplytext'] = 'Os campos traduzidos selecionados serão gravados no curso original. Campos alterados desde a tradução serão rejeitados.';
$string['confirmcopy'] = 'Confirmar cópia traduzida';
$string['confirmcopytext'] = 'Será criada uma cópia oculta do curso e, depois, as traduções selecionadas serão aplicadas nela. O curso original não será modificado.';
$string['copyfullname'] = 'Nome completo da cópia';
$string['copyshortname'] = 'Nome breve da cópia';
$string['copycreated'] = 'Cópia traduzida do curso criada.';
$string['applied'] = '{$a} campos traduzidos aplicados.';
$string['translatedcount'] = '{$a} campos traduzidos.';
$string['translationpartial'] = 'O provedor devolveu apenas parte do mapeamento JSON solicitado. As traduções disponíveis foram salvas e os itens ausentes permaneceram pendentes ou com erro.';
$string['nonelected'] = 'Selecione pelo menos um item.';
$string['pending'] = 'Pendente';
$string['translated'] = 'Traduzido';
$string['failed'] = 'Erro';
$string['outdated'] = 'Alterado depois da tradução';
$string['appliedstatus'] = 'Aplicado';
$string['current'] = 'Atual';
$string['missing'] = 'Não é mais resolvível';
$string['invalidresponse'] = 'O provedor de IA retornou uma resposta JSON inválida.';
$string['missingtranslation'] = 'A tradução não veio na resposta da IA.';
$string['bridgeerror'] = 'O AI Bridge não conseguiu concluir a tradução: {$a}';
$string['protectionerror'] = 'Tokens protegidos ou a estrutura HTML foram alterados durante a tradução: {$a}';
$string['staleitem'] = 'Este campo mudou depois do snapshot da tradução e não foi aplicado.';
$string['missingitem'] = 'Este campo não pode mais ser localizado na estrutura atual do curso.';
$string['unsupportedfield'] = 'A gravação foi recusada porque a combinação tabela/campo não é suportada.';
$string['copyapplywarning'] = 'A cópia foi criada, mas {$a} campos traduzidos não puderam ser mapeados ou aplicados.';
$string['applyskipwarning'] = '{$a} campos selecionados foram ignorados porque mudaram, desapareceram, não podem ser gravados com segurança ou pertencem a conteúdo versionado do banco de questões.';
$string['copyhidden'] = 'A cópia traduzida é criada oculta para que possa ser revisada antes da publicação.';
$string['backtojob'] = 'Voltar ao trabalho de tradução';
$string['newjob'] = 'Nova tradução';
$string['items'] = 'Campos';
$string['privacy:metadata:job'] = 'Armazena quem criou um trabalho de tradução de curso e os metadados de idioma/configuração.';
$string['privacy:metadata:job:userid'] = 'Usuário que criou o trabalho de tradução.';
$string['privacy:metadata:job:courseid'] = 'Curso que está sendo traduzido.';
$string['privacy:metadata:job:sourcelang'] = 'Código do idioma de origem.';
$string['privacy:metadata:job:targetlang'] = 'Código do idioma de destino.';
$string['privacy:metadata:job:timecreated'] = 'Momento em que o trabalho de tradução foi criado.';
$string['privacy:path'] = 'Trabalhos de tradução de curso';
$string['summary:pending'] = 'Pendentes: {$a}';
$string['summary:translated'] = 'Traduzidos: {$a}';
$string['summary:failed'] = 'Com erro: {$a}';
$string['summary:outdated'] = 'Desatualizados: {$a}';
$string['summary:applied'] = 'Aplicados: {$a}';
$string['refreshhint'] = 'Se um campo de origem mudou, traduzi-lo novamente atualiza o snapshot antes de enviá-lo à IA.';
$string['onlycoursecontent'] = 'Somente conteúdo autoral do curso é enviado para tradução. Submissões, posts de fórum, tentativas de quiz, notas e outros dados privados de alunos nunca são coletados.';
$string['emptycourse'] = 'Nenhum campo traduzível suportado foi encontrado com as opções selecionadas.';
$string['error:samelanguage'] = 'Os idiomas de origem e destino precisam ser diferentes.';
$string['error:invalidterminology'] = 'Linha de terminologia inválida: {$a}. Use Origem = Destino.';
$string['error:bridgeunavailable'] = 'A API obrigatória local_ai_bridge não está disponível.';
