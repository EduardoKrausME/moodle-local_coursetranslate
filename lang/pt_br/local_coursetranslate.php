<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// Este arquivo faz parte do Moodle - http://moodle.org/.

/**
 * Strings em português do Brasil.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['applied'] = '{$a} campos traduzidos aplicados.';
$string['appliedstatus'] = 'Aplicado';
$string['applyselected'] = 'Aplicar selecionados no curso original';
$string['applyskipwarning'] = '{$a} campos selecionados foram ignorados porque mudaram, desapareceram, não podem ser gravados com segurança ou pertencem a conteúdo versionado do banco de questões.';
$string['backtojob'] = 'Voltar ao trabalho de tradução';
$string['bridgeerror'] = 'O AI Bridge não conseguiu concluir a tradução: {$a}';
$string['confirmapply'] = 'Confirmar aplicação das traduções';
$string['confirmapplytext'] = 'Os campos traduzidos selecionados serão gravados no curso original. Campos alterados desde a tradução serão rejeitados.';
$string['confirmcopy'] = 'Confirmar cópia traduzida';
$string['confirmcopytext'] = 'Será criada uma cópia oculta do curso e, depois, as traduções selecionadas serão aplicadas nela. O curso original não será modificado.';
$string['copyapplywarning'] = 'A cópia foi criada, mas {$a} campos traduzidos não puderam ser mapeados ou aplicados.';
$string['copycreated'] = 'Cópia traduzida do curso criada.';
$string['copyfullname'] = 'Nome completo da cópia';
$string['copyhidden'] = 'A cópia traduzida é criada oculta para que possa ser revisada antes da publicação.';
$string['copyshortname'] = 'Nome breve da cópia';
$string['coursetranslate:translate'] = 'Traduzir conteúdo do curso';
$string['createcopy'] = 'Criar cópia traduzida do curso';
$string['current'] = 'Atual';
$string['emptycourse'] = 'Nenhum campo traduzível suportado foi encontrado com as opções selecionadas.';
$string['error:bridgeunavailable'] = 'A API obrigatória local_ai_bridge não está disponível.';
$string['error:invalidterminology'] = 'Linha de terminologia inválida: {$a}. Use Origem = Destino.';
$string['error:samelanguage'] = 'Os idiomas de origem e destino precisam ser diferentes.';
$string['failed'] = 'Erro';
$string['field'] = 'Campo';
$string['includeglossary'] = 'Incluir entradas de glossário gerenciadas por professores';
$string['invalidresponse'] = 'O provedor de IA retornou uma resposta JSON inválida.';
$string['items'] = 'Campos';
$string['job'] = 'Trabalho de tradução';
$string['jobcreated'] = 'Trabalho de tradução criado com {$a} campos.';
$string['missing'] = 'Não é mais resolvível';
$string['missingitem'] = 'Este campo não pode mais ser localizado na estrutura atual do curso.';
$string['missingtranslation'] = 'A tradução não veio na resposta da IA.';
$string['newjob'] = 'Nova tradução';
$string['nonelected'] = 'Selecione pelo menos um item.';
$string['onlycoursecontent'] = 'Somente conteúdo autoral do curso é enviado para tradução. Submissões, posts de fórum, tentativas de quiz, notas e outros dados privados de alunos nunca são coletados.';
$string['outdated'] = 'Alterado depois da tradução';
$string['pending'] = 'Pendente';
$string['pluginname'] = 'Tradutor de curso';
$string['privacy:metadata:job'] = 'Armazena quem criou um trabalho de tradução de curso e os metadados de idioma/configuração.';
$string['privacy:metadata:job:courseid'] = 'Curso que está sendo traduzido.';
$string['privacy:metadata:job:sourcelang'] = 'Código do idioma de origem.';
$string['privacy:metadata:job:targetlang'] = 'Código do idioma de destino.';
$string['privacy:metadata:job:timecreated'] = 'Momento em que o trabalho de tradução foi criado.';
$string['privacy:metadata:job:userid'] = 'Usuário que criou o trabalho de tradução.';
$string['privacy:path'] = 'Trabalhos de tradução de curso';
$string['protectionerror'] = 'Tokens protegidos ou a estrutura HTML foram alterados durante a tradução: {$a}';
$string['refreshhint'] = 'Se um campo de origem mudou, traduzi-lo novamente atualiza o snapshot antes de enviá-lo à IA.';
$string['select'] = 'Selecionar';
$string['source'] = 'Original';
$string['sourcelang'] = 'Idioma de origem';
$string['staleitem'] = 'Este campo mudou depois do snapshot da tradução e não foi aplicado.';
$string['starttranslation'] = 'Criar trabalho de tradução';
$string['status'] = 'Status';
$string['summary:applied'] = 'Aplicados: {$a}';
$string['summary:failed'] = 'Com erro: {$a}';
$string['summary:outdated'] = 'Desatualizados: {$a}';
$string['summary:pending'] = 'Pendentes: {$a}';
$string['summary:translated'] = 'Traduzidos: {$a}';
$string['targetlang'] = 'Idioma de destino';
$string['terminology'] = 'Terminologia obrigatória';
$string['terminology_help'] = 'Uma regra por linha no formato Origem = Destino. Esses termos são protegidos como tokens para que o termo de destino seja restaurado exatamente.';
$string['translated'] = 'Traduzido';
$string['translatedcount'] = '{$a} campos traduzidos.';
$string['translatefullname'] = 'Incluir nome completo do curso';
$string['translatepending'] = 'Traduzir campos pendentes';
$string['translateselected'] = 'Traduzir selecionados';
$string['translatesummary'] = 'Incluir resumo do curso';
$string['translation'] = 'Tradução';
$string['translationpartial'] = 'O provedor devolveu apenas parte do mapeamento JSON solicitado. As traduções disponíveis foram salvas e os itens ausentes permaneceram pendentes ou com erro.';
$string['unsupportedfield'] = 'A gravação foi recusada porque a combinação tabela/campo não é suportada.';
