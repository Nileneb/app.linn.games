{!! '%' !!} Generiert von app.linn.games — APA 7th Edition
\documentclass[stu,12pt,a4paper]{apa7}
\usepackage[american]{babel}
\usepackage{csquotes}
\usepackage[style=apa,backend=biber]{biblatex}
\usepackage{hyperref}
\usepackage{booktabs}
\usepackage{longtable}

\title{ {!! $projekt->titel !!} }
\authorsnames{}
\authorsaffiliations{}
\course{}
\professor{}
\duedate{\today}

\begin{document}

\maketitle

@foreach ($sections as $section)
\section{ {!! $section['title'] !!} }
{!! $section['content'] !!}

@endforeach

\end{document}
