<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese strings for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Envio com verificação de páginas';
$string['modulename'] = 'Envio com verificação de páginas';
$string['modulenameplural'] = 'Envios com verificação de páginas';
$string['modulename_help'] = 'A atividade de verificação de páginas recebe arquivos dos alunos como a atividade Tarefa e, além disso, conta as páginas do que foi enviado.

O professor define um intervalo de páginas, os tipos de arquivo aceitos e um conjunto de verificações do documento. O aluno que anexar um arquivo fora das regras é avisado na hora, na própria tela de envio, em vez de descobrir depois do prazo.

A contagem é exata para PDF. Para .docx e .pptx ela é lida das propriedades que o editor gravou no arquivo, que podem estar ausentes ou desatualizadas: por isso a atividade pode ser configurada para aceitar somente PDF quando a contagem precisa ser confiável.';
$string['pagecheckname'] = 'Nome da atividade';
$string['pluginadministration'] = 'Administração da verificação de páginas';

// Capabilities.
$string['pagecheck:addinstance'] = 'Adicionar uma nova atividade de verificação de páginas';
$string['pagecheck:view'] = 'Ver uma atividade de verificação de páginas';
$string['pagecheck:submit'] = 'Enviar arquivos para uma atividade de verificação de páginas';
$string['pagecheck:viewallsubmissions'] = 'Ver todos os envios';
$string['pagecheck:grade'] = 'Avaliar envios';
$string['pagecheck:manageoverrides'] = 'Gerenciar exceções de grupo e de usuário';
$string['pagecheck:submitwithissues'] = 'Enviar arquivos que descumprem as restrições';

// Settings form: availability.
$string['availability'] = 'Disponibilidade';
$string['allowsubmissionsfromdate'] = 'Permitir envios a partir de';
$string['allowsubmissionsfromdate_help'] = 'Os alunos não podem enviar nada antes desta data. A atividade e suas restrições continuam visíveis para eles.';
$string['duedate'] = 'Data de entrega';
$string['duedate_help'] = 'Envios feitos depois desta data são marcados como atrasados. Continuam sendo aceitos, a menos que "Recusar envios atrasados" esteja ligado ou a data limite tenha passado.';
$string['cutoffdate'] = 'Data limite';
$string['cutoffdate_help'] = 'Depois desta data nada é aceito, independentemente da data de entrega.';
$string['blockafterdue'] = 'Recusar envios atrasados';
$string['blockafterdue_help'] = 'Quando ligado, a própria data de entrega fecha a atividade. Deixe desligado para continuar aceitando trabalhos, marcando-os como atrasados.';
$string['maxattempts'] = 'Número máximo de tentativas';
$string['maxattempts_help'] = 'Quantas vezes o aluno pode enviar o trabalho para avaliação. Salvar um rascunho não consome tentativa.';
$string['unlimitedattempts'] = 'Ilimitado';
$string['requiresubmissionstatement'] = 'Exigir que o aluno aceite a declaração de autoria';
$string['requiresubmissionstatement_help'] = 'O aluno precisa confirmar que o trabalho é de sua autoria antes de os arquivos serem salvos.';
$string['submissionstatement'] = 'Este trabalho é de minha autoria, exceto onde reconheci o trabalho de outras pessoas.';

// Settings form: files.
$string['filesettings'] = 'Arquivos aceitos';
$string['allowedextensions'] = 'Tipos de arquivo aceitos';
$string['allowedextensions_help'] = 'Somente estes tipos podem ser anexados. As páginas são contadas com exatidão em PDF e lidas das propriedades gravadas no documento em .docx e .pptx; qualquer outro tipo é aceito mas não pode ser contado, e passa a ser tratado conforme a opção "Contagem de páginas desconhecida".';
$string['allowedextensions_desc'] = 'Os tipos de arquivo que as novas atividades aceitam por padrão.';
$string['maxfiles'] = 'Número máximo de arquivos';
$string['maxbytes'] = 'Tamanho máximo do arquivo';
$string['maxbytes_help'] = 'O tamanho máximo de cada arquivo anexado. O limite de upload do curso continua valendo além deste.';
$string['anyfiletype'] = 'Qualquer tipo de arquivo';

// Settings form: pages.
$string['pagesettings'] = 'Restrições de páginas';
$string['minpages'] = 'Mínimo de páginas';
$string['minpages_help'] = 'O envio precisa ter pelo menos este número de páginas, somando todos os arquivos anexados. Zero significa sem mínimo.';
$string['maxpages'] = 'Máximo de páginas';
$string['maxpages_help'] = 'O envio pode ter no máximo este número de páginas, somando todos os arquivos anexados. Zero significa sem máximo.';
$string['countcover'] = 'Páginas não contadas';
$string['countcover_help'] = 'Quantas páginas do início de cada arquivo ficam fora da contagem, por exemplo uma capa ou folha de rosto.';
$string['strictness'] = 'Quando uma restrição é descumprida';
$string['strictness_help'] = 'Se o envio que descumpre uma restrição de páginas é recusado ou apenas sinalizado. Prazos, tipos de arquivo, tamanho e número de tentativas são sempre exigidos, qualquer que seja a opção escolhida aqui.';
$string['strictness_block'] = 'Recusar o envio';
$string['strictness_warn'] = 'Aceitar e avisar o aluno';
$string['unknownpolicy'] = 'Contagem de páginas desconhecida';
$string['unknownpolicy_help'] = 'O que fazer com um arquivo cujas páginas não podem ser contadas, por exemplo um .docx salvo por uma ferramenta que não registra o número de páginas.';
$string['unknownpolicy_warn'] = 'Aceitar e avisar o aluno';
$string['unknownpolicy_accept'] = 'Aceitar sem avisar';
$string['unknownpolicy_reject'] = 'Recusar';

// Settings form: document checks.
$string['documentchecks'] = 'Verificações do documento';
$string['rejectencrypted'] = 'Recusar arquivos protegidos por senha';
$string['rejectencrypted_help'] = 'Um arquivo criptografado não pode ser lido e, muitas vezes, o avaliador também não consegue abri-lo.';
$string['requiretextlayer'] = 'Exigir texto selecionável';
$string['requiretextlayer_help'] = 'Recusa um PDF que seja apenas a imagem de uma página, como uma fotografia ou um documento digitalizado sem reconhecimento de caracteres. A verificação procura instruções de desenho de texto no documento, de modo que um PDF cujo texto foi convertido em curvas pode ser sinalizado mesmo parecendo normal na tela.';
$string['rejectblankpages'] = 'Sinalizar páginas em branco';
$string['rejectblankpages_help'] = 'Avisa quando o documento contém páginas que não desenham nada. É um aviso e não uma recusa, porque uma página com um gráfico incomum pode parecer em branco para a verificação.';
$string['blankpagetolerance'] = 'Páginas em branco toleradas';

// Settings form: groups and completion.
$string['groupsettings'] = 'Envio em grupo';
$string['teamsubmission'] = 'Alunos enviam em grupo';
$string['teamsubmission_help'] = 'Um único envio é compartilhado pelo grupo inteiro. Qualquer integrante pode anexar arquivos e qualquer integrante pode enviar o trabalho para avaliação.';
$string['completionsubmit'] = 'O aluno precisa enviar o trabalho para avaliação';
$string['completionsubmit_help'] = 'A atividade é considerada concluída assim que o aluno envia uma tentativa para avaliação.';
$string['completionsubmit_desc'] = 'Enviar o trabalho para avaliação';

// Activity page.
$string['submissionstatus'] = 'Situação do envio';
$string['restrictions'] = 'Restrições';
$string['submittedfiles'] = 'Arquivos enviados';
$string['submissionfiles'] = 'Arquivos';
$string['submissionfiles_help'] = 'Anexe os arquivos desta atividade. Eles são conferidos com as restrições acima assim que você os escolhe, e novamente quando são salvos.';
$string['file'] = 'Arquivo';
$string['pages'] = 'Páginas';
$string['size'] = 'Tamanho';
$string['countmethod'] = 'Contado a partir de';
$string['totalpages'] = 'Total de páginas';
$string['timesubmitted'] = 'Enviado para avaliação';
$string['issues'] = 'Verificações';
$string['addsubmission'] = 'Adicionar envio';
$string['editsubmission'] = 'Editar envio';
$string['submitforgrading'] = 'Enviar para avaliação';
$string['confirmsubmission'] = 'Depois de enviar este trabalho para avaliação você não poderá mais alterá-lo. Tem certeza?';
$string['alreadysubmitted'] = 'Este trabalho já foi enviado para avaliação.';
$string['newattempt'] = 'Iniciar nova tentativa';
$string['confirmnewattempt'] = 'O trabalho já enviado continua registrado, e a nova tentativa começa vazia. Deseja continuar?';
$string['errornonewattempt'] = 'Você não pode iniciar outra tentativa nesta atividade.';
$string['submissionsent'] = 'Seu trabalho foi enviado para avaliação.';
$string['submissionrefused'] = 'Este envio não foi aceito.';
$string['filessaved'] = 'Seus arquivos foram salvos. Eles ainda não foram enviados para avaliação.';
$string['checkingfile'] = 'Conferindo o arquivo...';
$string['checkonserver'] = 'este arquivo será conferido quando terminar o envio.';

$string['status_new'] = 'Nada enviado ainda';
$string['status_draft'] = 'Rascunho, não enviado para avaliação';
$string['status_submitted'] = 'Enviado para avaliação';
$string['status_reopened'] = 'Reaberto para nova tentativa';

$string['pagesbetween'] = 'Entre {$a->min} e {$a->max}';
$string['pagesatleast'] = 'No mínimo {$a}';
$string['pagesatmost'] = 'No máximo {$a}';
$string['pagesnolimit'] = 'Sem limite';
$string['pagesunknown'] = 'Não foi possível contar';
$string['pagescounted'] = '{$a->counted} contadas, de {$a->total}';

$string['method_unknown'] = 'Não contado';
$string['method_fpdi'] = 'Árvore de páginas do PDF';
$string['method_raw'] = 'Estrutura do PDF';
$string['method_gs'] = 'Ghostscript';
$string['method_ooxml'] = 'Propriedades do documento';
$string['method_image'] = 'Uma página por imagem';

// Issues.
$string['issuewithfile'] = '{$a->filename}: {$a->message}';
$string['issue_nofiles'] = 'Nenhum arquivo foi anexado.';
$string['issue_toofewpages'] = 'o envio tem {$a->count} páginas, mas são exigidas pelo menos {$a->min}.';
$string['issue_toomanypages'] = 'o envio tem {$a->count} páginas, mas são permitidas no máximo {$a->max}.';
$string['issue_badextension'] = 'arquivos do tipo .{$a->extension} não são aceitos aqui. Tipos aceitos: {$a->allowed}.';
$string['issue_toolarge'] = 'o arquivo tem {$a->size} e o limite é {$a->max}.';
$string['issue_toomanyfiles'] = 'foram anexados {$a->count} arquivos, e o máximo permitido é {$a->max}.';
$string['issue_encrypted'] = 'o arquivo está protegido por senha e não pode ser lido.';
$string['issue_unreadable'] = 'não foi possível ler o arquivo ({$a}).';
$string['issue_unknownpagecount'] = 'não foi possível determinar o número de páginas deste arquivo. Salve-o como PDF se a contagem precisar ser conferida.';
$string['issue_notextlayer'] = 'este documento não tem texto selecionável. Uma digitalização ou fotografia não é aceita aqui.';
$string['issue_blankpages'] = 'foram encontradas {$a->count} páginas em branco, e são toleradas {$a->tolerance}.';
$string['issue_late'] = 'este envio está atrasado. A data de entrega era {$a}.';
$string['issue_notopenyet'] = 'esta atividade só aceita envios a partir de {$a}.';
$string['issue_submissionsclosed'] = 'esta atividade parou de aceitar envios em {$a}.';
$string['issue_noattemptsleft'] = 'você já usou todas as tentativas permitidas ({$a}).';

// Errors raised while reading a file.
$string['errorfileunreadable'] = 'não foi possível abrir o arquivo';
$string['errorfiletoolarge'] = 'o arquivo é grande demais para ser inspecionado';
$string['errornotapdf'] = 'este arquivo não é um PDF';
$string['errorunreadablepdf'] = 'a estrutura do PDF está danificada';
$string['errornozip'] = 'este servidor não consegue abrir documentos do Office';
$string['errorunreadableooxml'] = 'não foi possível abrir o documento do Office';
$string['errorencrypted'] = 'o arquivo está protegido por senha';
$string['errorunsupportedformat'] = 'não é possível contar páginas neste tipo de arquivo';

// Form validation.
$string['errorduebeforeopen'] = 'A data de entrega precisa ser posterior à data de abertura dos envios.';
$string['errorcutoffbeforedue'] = 'A data limite precisa ser posterior à data de entrega.';
$string['errornegativepages'] = 'Um número de páginas não pode ser negativo.';
$string['errormaxbelowmin'] = 'O número máximo de páginas precisa ser pelo menos igual ao mínimo.';
$string['errorcovertoolarge'] = 'As páginas fora da contagem precisam ser menos que o máximo.';
$string['errornoextensions'] = 'É preciso aceitar pelo menos um tipo de arquivo.';
$string['errorstatementrequired'] = 'Você precisa aceitar a declaração de autoria.';
$string['errorcannotedit'] = 'Você não pode mais alterar este envio.';
$string['errornothingtosubmit'] = 'Ainda não há nada para enviar para avaliação.';
$string['errornotargets'] = 'Não há ninguém para quem criar uma exceção.';

// Teacher pages.
$string['viewsubmissions'] = 'Ver envios';
$string['summarysubmitted'] = '{$a->submitted} de {$a->participants} participantes enviaram o trabalho para avaliação.';
$string['nosubmissions'] = 'Nada a exibir.';
$string['notenrolled'] = 'Não inscrito';
$string['noinstances'] = 'Não há atividades de verificação de páginas neste curso.';
$string['savegrades'] = 'Salvar notas';
$string['gradessaved'] = '{$a} notas salvas.';
$string['gradefor'] = 'Nota de {$a}';
$string['exportcsv'] = 'Baixar em CSV';
$string['filterall'] = 'Todos';
$string['filtersubmitted'] = 'Enviados para avaliação';
$string['filternotsubmitted'] = 'Não enviados para avaliação';
$string['filterwithissues'] = 'Com verificações reprovadas';
$string['deleteallsubmissions'] = 'Excluir todos os envios';

// Overrides.
$string['overrides'] = 'Exceções';
$string['nooverrides'] = 'Nenhuma exceção foi definida.';
$string['addgroupoverride'] = 'Adicionar exceção de grupo';
$string['adduseroverride'] = 'Adicionar exceção de usuário';
$string['overridegroup'] = 'Grupo';
$string['overrideuser'] = 'Aluno';
$string['overridefor'] = 'Aplica-se a';
$string['overridegrouplabel'] = 'Grupo: {$a}';
$string['overrideuserlabel'] = 'Aluno: {$a}';
$string['overridemissingtarget'] = 'O grupo ou aluno não existe mais';
$string['overridethis'] = 'Substituir';
$string['overridesaved'] = 'A exceção foi salva.';
$string['overridedeleted'] = 'A exceção foi excluída.';

// Calendar.
$string['calendaropen'] = '{$a} abre';
$string['calendardue'] = '{$a} tem entrega';

// Events.
$string['eventsubmissioncreated'] = 'Arquivos do envio salvos';
$string['eventsubmissionsubmitted'] = 'Envio enviado para avaliação';
$string['eventsubmissionrejected'] = 'Envio recusado';

// Site settings.
$string['useghostscript'] = 'Usar o Ghostscript como último recurso';
$string['useghostscript_desc'] = 'Quando um PDF não puder ser lido por nenhum dos dois analisadores internos, pedir ao Ghostscript que conte suas páginas. Usa o caminho definido na configuração "Caminho para o ghostscript" e vem desligado por padrão.';

// Privacy.
$string['privacy:submissionpath'] = 'Envios';
$string['privacy:gradepath'] = 'Nota';
$string['privacy:metadata:submissions'] = 'As tentativas que o aluno fez nesta atividade.';
$string['privacy:metadata:submissions:userid'] = 'O aluno a quem a tentativa pertence.';
$string['privacy:metadata:submissions:groupid'] = 'O grupo a quem a tentativa pertence, em envios de grupo.';
$string['privacy:metadata:submissions:attemptnumber'] = 'Qual tentativa é esta.';
$string['privacy:metadata:submissions:status'] = 'Se a tentativa é rascunho ou foi enviada para avaliação.';
$string['privacy:metadata:submissions:totalpages'] = 'Quantas páginas a tentativa somou.';
$string['privacy:metadata:submissions:timecreated'] = 'Quando a tentativa foi iniciada.';
$string['privacy:metadata:submissions:timemodified'] = 'Quando a tentativa foi alterada pela última vez.';
$string['privacy:metadata:submissions:timesubmitted'] = 'Quando a tentativa foi enviada para avaliação.';
$string['privacy:metadata:files'] = 'O que foi encontrado em cada arquivo enviado.';
$string['privacy:metadata:files:filename'] = 'O nome do arquivo.';
$string['privacy:metadata:files:filesize'] = 'O tamanho do arquivo.';
$string['privacy:metadata:files:pagecount'] = 'Quantas páginas o arquivo somou.';
$string['privacy:metadata:files:status'] = 'Se o arquivo passou nas verificações.';
$string['privacy:metadata:grades'] = 'As notas atribuídas nesta atividade.';
$string['privacy:metadata:grades:userid'] = 'O aluno que recebeu a nota.';
$string['privacy:metadata:grades:grade'] = 'A nota.';
$string['privacy:metadata:grades:feedback'] = 'O comentário que o professor escreveu para o aluno.';
$string['privacy:metadata:grades:grader'] = 'O professor que atribuiu a nota.';
$string['privacy:metadata:grades:timemodified'] = 'Quando a nota foi alterada pela última vez.';
$string['privacy:metadata:overrides'] = 'As restrições alteradas para alunos específicos.';
$string['privacy:metadata:overrides:userid'] = 'O aluno a quem a exceção se aplica.';
$string['privacy:metadata:overrides:duedate'] = 'A data de entrega que substitui a da atividade.';
$string['privacy:metadata:overrides:cutoffdate'] = 'A data limite que substitui a da atividade.';
$string['privacy:metadata:overrides:maxattempts'] = 'O número de tentativas que substitui o da atividade.';
$string['privacy:metadata:filepurpose'] = 'Os arquivos que o aluno anexou a um envio.';

// Paper size.
$string['pagesize'] = 'Tamanho de página exigido';
$string['pagesize_help'] = 'Recusa um PDF cujas páginas não estejam no tamanho em que o trabalho deve ser entregue. O tamanho é lido da própria página, então um documento exportado no papel errado é pego aqui, e não na hora de imprimir. Não é possível verificar em .docx e .pptx.';
$string['pagesize_any'] = 'Qualquer tamanho';
$string['pagesize_a4'] = 'A4';
$string['pagesize_a3'] = 'A3';
$string['pagesize_a5'] = 'A5';
$string['pagesize_letter'] = 'Carta';
$string['pagesize_legal'] = 'Ofício';
$string['pagesize_mixed'] = 'Tamanhos misturados';
$string['pagesize_unknown'] = 'Não reconhecido';

// Counting mode and further file restrictions.
$string['countmode'] = 'Aplicar o intervalo de páginas a';
$string['countmode_help'] = 'Se o mínimo e o máximo de páginas descrevem o envio inteiro ou cada arquivo anexado separadamente. Com apenas um arquivo permitido, as duas opções dão no mesmo.';
$string['countmode_total'] = 'O envio inteiro';
$string['countmode_perfile'] = 'Cada arquivo separadamente';
$string['minfiles'] = 'Número mínimo de arquivos';
$string['minfiles_help'] = 'Quantos arquivos o aluno precisa anexar antes de poder enviar o trabalho para avaliação.';
$string['nominimum'] = 'Sem mínimo';
$string['filenamepattern'] = 'Nome de arquivo exigido';
$string['filenamepattern_help'] = 'Um padrão que todo arquivo anexado precisa seguir, útil quando os trabalhos são organizados pelo nome. Use * para qualquer sequência de caracteres e ? para um único, por exemplo <code>TCC_*.pdf</code>. Deixe vazio para aceitar qualquer nome.';
$string['rejectduplicates'] = 'Recusar o mesmo arquivo anexado duas vezes';
$string['rejectduplicates_help'] = 'Compara o conteúdo dos arquivos anexados, então o mesmo documento enviado com dois nomes também é pego.';

// Issues for the new checks.
$string['issue_badpagesize'] = 'as páginas estão em {$a->found}, e o exigido é {$a->expected}.';
$string['issue_badfilename'] = 'o nome do arquivo não segue o padrão exigido ({$a}).';
$string['issue_toofewfiles'] = 'foram anexados {$a->count} arquivos, e são exigidos pelo menos {$a->min}.';
$string['issue_duplicatefile'] = 'este é o mesmo arquivo que {$a}, anexado duas vezes.';

$string['errorminfilesabovemax'] = 'O número mínimo de arquivos não pode ser maior que o máximo.';
$string['errorpatternnowildcard'] = 'Um padrão sem curinga só corresponde a um nome exato. Acrescente * ou ? , ou uma extensão como .pdf.';
$string['papersize'] = 'Tamanho da página';

// The page count meter.
$string['nofilesyet'] = 'Nada anexado ainda. Use o botão abaixo para adicionar seu trabalho.';
$string['meterinrange'] = 'Dentro do intervalo exigido.';
$string['meternorange'] = 'Esta atividade não define limite de páginas.';
$string['metershort'] = 'Faltam {$a} páginas.';
$string['meterover'] = '{$a} páginas acima do limite.';
$string['metercannotcount'] = 'Não foi possível contar as páginas deste envio.';
$string['filesbetween'] = 'Entre {$a->min} e {$a->max}';

// Grading.
$string['gradeheading'] = 'Avaliação';
$string['gradeverb'] = 'Avaliar';
$string['gradeoutof'] = 'Nota de 0 a {$a}';
$string['gradeentry'] = 'Nota';
$string['gradeentry_help'] = 'Deixe em branco para retirar a nota. A nota vai para o livro de notas assim que é salva.';
$string['feedback'] = 'Comentário para o aluno';
$string['feedback_help'] = 'O aluno lê isto na tela da atividade, junto com a nota. Também vai para o livro de notas.';
$string['savechangesandnext'] = 'Salvar e ir para o próximo aluno';
$string['gradesaved'] = 'A nota de {$a} foi salva.';
$string['gradedon'] = 'Avaliado pela última vez em {$a->date} por {$a->grader}.';
$string['gradedon_short'] = 'Avaliado em';
$string['graded'] = 'Avaliado';
$string['notgraded'] = 'Sem nota';
$string['attempt'] = 'Tentativa';
$string['attempthistory'] = 'Tentativas anteriores';
$string['studentxofy'] = 'Aluno {$a->position} de {$a->total}';
$string['nextstudent'] = 'Próximo aluno';
$string['previousstudent'] = 'Aluno anterior';
$string['errorgradeoutofrange'] = 'A nota precisa ser um número entre 0 e {$a}.';
$string['errorgradeinvalid'] = 'Essa não é uma das notas que esta atividade oferece.';

// The screens.
$string['gradelabel'] = 'Nota';
$string['timeline'] = 'Andamento';
$string['step_draft'] = 'Rascunho';
$string['step_submitted'] = 'Enviado';
$string['step_graded'] = 'Avaliado';
$string['stepdone'] = 'concluído';
$string['stepcurrent'] = 'etapa atual';
$string['steptodo'] = 'ainda por vir';
$string['minimumshort'] = 'mín {$a}';
$string['maximumshort'] = 'máx {$a}';
$string['ofmax'] = 'de {$a}';
$string['checkspassed'] = 'Todas as verificações passaram';
$string['gradedonshort'] = 'Avaliado em {$a}';
$string['status_graded'] = 'Avaliado';
