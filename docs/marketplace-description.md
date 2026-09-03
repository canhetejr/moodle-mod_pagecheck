Submission with page check collects student work the way the standard Assignment activity does, and additionally counts the pages of what is handed in. When a file breaks one of the rules the teacher set, the student is told immediately — in the browser, before the upload even finishes — and the server refuses or flags it, depending on how strict the activity is configured.

Length requirements are common in coursework, and until somebody opens the file nobody knows whether they were met. This activity closes that gap: the student sees a page count against the required range on their own screen, and the teacher sees it for the whole class at a glance.

WHAT IT CHECKS

Pages — a minimum, a maximum, and how many leading pages to leave out of the count for a cover sheet. The range can apply to the submission as a whole or to each attached file on its own.

Paper size — A4, A3, A5, Letter or Legal, read from each page of the PDF itself, so work exported to the wrong paper is caught here rather than at the printer.

Files — accepted types, maximum size, a minimum and maximum number of files, a required file name pattern using * and ?, and refusing the same file attached twice (compared by content, so a rename does not slip past).

Dates and attempts — when submissions open, a due date, a hard cut-off, whether late work is refused outright, how many attempts a student may send, and a submission statement.

Document checks — refusing password protected files, requiring selectable text (so a photograph or an unrecognised scan is rejected), and flagging blank pages.

Class and exceptions — group submission, plus per group and per user overrides for dates, attempts and page limits.

Finally, the teacher chooses what happens when a page rule is broken: refuse the submission, or accept it and warn the student. Deadlines, file types, sizes and the attempt allowance are always enforced, whichever option is chosen.

GRADING

A grading screen shows one student at a time: the submission exactly as the student sees it — page meter, files, paper size and the checks that failed — alongside a grade and a comment. Previous and next walk the class in the order of the report, and "save and go to the next student" means a class can be marked without returning to the list. Grade and comment reach the gradebook, and the student reads both on the activity page. Scales are supported and are shown by the name of the scale item.

HOW PAGES ARE COUNTED

PDF is counted exactly, using the FPDI parser Moodle already bundles, falling back to a direct read of the file structure (which also counts encrypted documents, whose structure stays readable) and, if the administrator turns it on, to Ghostscript. Everything runs in pure PHP: no pdfinfo, pdftotext or Ghostscript installation is required.

DOCX and PPTX counts are read from the document properties the editor stored in the file.

KNOWN LIMITATIONS

Please read these before configuring the activity, because they decide how you should set it up.

The count for .docx and .pptx is whatever the editor recorded when the file was last saved, not a rendering. Word writes it; many converters and Google Docs exports write nothing at all, and a file edited afterwards by another tool can carry a stale number. When no number is present the plugin reports "unknown" rather than guessing, and the "unknown page count" setting decides what to do. If the count has to be reliable, accept PDF only.

Paper size can only be measured in PDF. For .docx and .pptx the restriction simply does not apply, rather than the file being accused of a size that could not be measured.

"Require selectable text" and "flag blank pages" are heuristics. The first looks for text drawing instructions, so a PDF whose text was converted to outlines is flagged even though it looks fine on screen. Blank pages are therefore reported as a warning, never as a refusal.

A PDF that stores its objects in compressed object streams is counted correctly, but is not analysed for a text layer or blank pages; the plugin reports "unknown" instead of answering wrongly.

PRIVACY AND ACCESSIBILITY

The plugin implements the Moodle privacy API: submissions, page counts, grades, comments and overrides are described, exported and deleted through the standard requests. Backup and restore are supported. Every animation on the status screens sits behind prefers-reduced-motion.

The interface ships in English and Brazilian Portuguese.
