# mod_pagecheck — Envio com verificação de páginas

Atividade do Moodle que recebe arquivos como a Tarefa (`mod_assign`) e **conta o número de
páginas do que o aluno envia**. Se o arquivo estiver fora das regras que o professor configurou,
o aluno é avisado na hora — no navegador, antes de o envio terminar — e o servidor recusa (ou
apenas sinaliza, se o professor preferir) quando o trabalho é salvo.

Compatível com **Moodle 4.1 LTS até a série 5.x**.

## O que ele verifica

| Categoria | Restrições |
|---|---|
| Páginas | mínimo, máximo, páginas de capa fora da contagem, e se o intervalo vale para o envio inteiro ou para cada arquivo |
| Papel | tamanho de página exigido (A4, A3, A5, Carta, Ofício), lido da própria página do PDF |
| Arquivos | tipos aceitos, tamanho máximo, quantidade mínima e máxima, padrão de nome de arquivo, recusar o mesmo arquivo anexado duas vezes |
| Prazos | abertura, entrega, data limite, recusar atrasados |
| Tentativas | número máximo de envios, declaração de autoria |
| Documento | recusar arquivo protegido por senha, exigir texto selecionável, sinalizar páginas em branco |
| Turma | envio em grupo, e exceções por grupo ou por aluno para datas, tentativas e limites de páginas |

O professor ainda escolhe **o que acontece quando uma regra de páginas é descumprida**: recusar o
envio ou aceitá-lo com um aviso. Prazos, tipos de arquivo, tamanho e tentativas são sempre
exigidos, qualquer que seja essa escolha.

## Como as páginas são contadas

| Formato | Fonte da contagem | Confiabilidade |
|---|---|---|
| PDF | FPDI (já embutido no Moodle); na falha, leitura direta da estrutura do arquivo; opcionalmente Ghostscript | Exata |
| PDF (tamanho do papel) | `/MediaBox` de cada página | Exata |
| DOCX / PPTX | `docProps/app.xml` (`<Pages>` / `<Slides>`) | Aproximada — ver limitações |
| Outros | — | Não contado |

Tudo é feito **em PHP puro**: não é preciso instalar `pdfinfo`, `pdftotext` nem Ghostscript. O
Ghostscript é apenas um último recurso opcional, desligado por padrão.

### Limitações conhecidas (leia antes de configurar)

- **A contagem de DOCX/PPTX é o que o editor gravou no arquivo**, não uma renderização. O Word
  grava `<Pages>` ao salvar; muitos conversores e exportações do Google Docs não gravam nada, e um
  arquivo editado depois por outra ferramenta pode carregar um número desatualizado. Quando o
  número não existe, o plugin devolve "desconhecido" em vez de chutar, e a opção **Contagem de
  páginas desconhecida** decide o que fazer. Se a contagem precisa ser confiável, **aceite somente
  PDF**.
- **"Exigir texto selecionável" e "sinalizar páginas em branco" são heurísticas.** A primeira
  procura instruções de desenho de texto: um PDF cujo texto foi convertido em curvas é sinalizado
  mesmo parecendo normal. A segunda considera em branco a página que não desenha texto, imagem nem
  traço. Por isso páginas em branco saem sempre como **aviso**, nunca como recusa.
- **O tamanho do papel só é verificável em PDF.** Em .docx e .pptx o plugin não tem como medir a
  página, então essa restrição simplesmente não se aplica a esses formatos — o arquivo não é
  acusado de estar no tamanho errado por algo que não deu para medir.
- **Um PDF que guarda seus objetos em fluxos comprimidos** (`/ObjStm`) é contado pelo FPDI, mas não
  é analisado quanto a texto e páginas em branco: nesses casos o plugin informa "desconhecido" em
  vez de responder errado.

## Avaliação

O professor abre **Ver envios** e clica em **Avaliar** num aluno. A tela mostra, lado a lado, o
trabalho como o aluno o vê — medidor de páginas, arquivos, tamanho de papel e as verificações que
falharam — e o formulário de nota e comentário. Há navegação **anterior / próximo** e um botão
**Salvar e ir para o próximo**, para corrigir uma turma sem voltar à lista a cada aluno.

Nota e comentário vão para o livro de notas, e o aluno passa a ver os dois na tela da atividade.
Atividades configuradas com **escala** são avaliadas pelo nome do item, não por número.

## Instalação

O diretório do plugin dentro do Moodle precisa se chamar exatamente `pagecheck` — é assim que o
Moodle liga os arquivos ao componente `mod_pagecheck`.

> ⚠️ O ZIP de **"Download source code"** do GitHub **não** funciona no instalador: ele vem com a
> pasta raiz nomeada a partir do repositório e da branch, e o Moodle recusa com
> *"Invalid plugin name"*. Use um dos caminhos abaixo.

### Pela interface (ZIP)

1. Baixe `pagecheck.zip` — nos artefatos da execução do CI, ou anexado a uma release.
2. **Administração do site → Plugins → Instalar plugins**, envie o arquivo ZIP.
3. Confirme a instalação.

### Por git (recomendado em servidor)

```bash
cd /caminho/do/moodle/mod
git clone https://github.com/canhetejr/TCC-M.git pagecheck
```

Depois acesse **Administração do site → Notificações** para concluir a instalação.

### Gerando o ZIP localmente

```bash
./tools/package.sh          # empacota o HEAD em dist/pagecheck.zip
./tools/package.sh v1.0.0   # ou uma tag específica
```

O script usa `git archive`, então o pacote contém exatamente os arquivos versionados: nada de
sobras não commitadas entrarem sem querer. Se a árvore de trabalho estiver suja, ele avisa e para.

### Configuração opcional

**Administração do site → Plugins → Atividades → Envio com verificação de páginas** define os
tipos de arquivo padrão e liga o uso do Ghostscript.

## Verificação manual

Depois de instalar, crie uma atividade com **mínimo 5** e **máximo 10** páginas e teste:

| Envio | Resultado esperado |
|---|---|
| PDF de 3 páginas | recusado, com aviso já na tela de upload |
| PDF de 7 páginas | aceito |
| PDF protegido por senha | recusado (com "Recusar arquivos protegidos por senha" ligado) |
| DOCX sem contagem gravada | tratado conforme "Contagem de páginas desconhecida" |
| Envio após a data limite | recusado |
| PDF em Carta com A4 exigido | recusado, com aviso já na tela de upload |
| Arquivo fora do padrão de nome | recusado |
| O mesmo PDF anexado duas vezes | recusado quando a opção está ligada |

Nenhuma dessas verificações depende do navegador: desligar o JavaScript muda apenas *quando* o
aluno é avisado, nunca *o que* é aceito.

## Desenvolvimento

Os testes automatizados usam [moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/) e
rodam no GitHub Actions contra Moodle 4.1, 4.5 e 5.0.

```bash
moodle-plugin-ci phpunit
moodle-plugin-ci behat --profile chrome
moodle-plugin-ci phpcs --max-warnings 0
```

Os documentos de teste (PDF, DOCX, PPTX) são **gerados em tempo de execução** por
`tests/fixtures/file_builder.php`, e não commitados como binários — assim cada byte de que os
testes dependem fica visível no código.

Ao alterar `amd/src/validator.js`, regenere o arquivo de produção:

```bash
moodle-plugin-ci grunt
```

## Licença

GNU GPL v3 ou posterior.
