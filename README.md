# mod_pagecheck — Envio com verificação de páginas

Atividade do Moodle que recebe arquivos como a Tarefa (`mod_assign`) e **conta o número de
páginas do que o aluno envia**. Se o arquivo estiver fora das regras que o professor configurou,
o aluno é avisado na hora — no navegador, antes de o envio terminar — e o servidor recusa (ou
apenas sinaliza, se o professor preferir) quando o trabalho é salvo.

Compatível com **Moodle 4.1 LTS até a série 5.x**.

## O que ele verifica

| Categoria | Restrições |
|---|---|
| Páginas | mínimo, máximo, páginas de capa fora da contagem |
| Arquivos | tipos aceitos, tamanho máximo, quantidade máxima |
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
- **Um PDF que guarda seus objetos em fluxos comprimidos** (`/ObjStm`) é contado pelo FPDI, mas não
  é analisado quanto a texto e páginas em branco: nesses casos o plugin informa "desconhecido" em
  vez de responder errado.

## Instalação

1. Copie o conteúdo deste repositório para `mod/pagecheck/` dentro do seu Moodle.
2. Acesse **Administração do site → Notificações** e conclua a instalação.
3. Opcional: **Administração do site → Plugins → Atividades → Envio com verificação de páginas**
   define os tipos de arquivo padrão e liga o uso do Ghostscript.

```bash
cd /caminho/do/moodle/mod
git clone https://github.com/canhetejr/TCC-M.git pagecheck
```

## Verificação manual

Depois de instalar, crie uma atividade com **mínimo 5** e **máximo 10** páginas e teste:

| Envio | Resultado esperado |
|---|---|
| PDF de 3 páginas | recusado, com aviso já na tela de upload |
| PDF de 7 páginas | aceito |
| PDF protegido por senha | recusado (com "Recusar arquivos protegidos por senha" ligado) |
| DOCX sem contagem gravada | tratado conforme "Contagem de páginas desconhecida" |
| Envio após a data limite | recusado |

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
