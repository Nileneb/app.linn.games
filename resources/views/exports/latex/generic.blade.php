{!! '%' !!} Generiert von app.linn.games — Systematisches Literaturreview
\documentclass[12pt,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage[ngerman,english]{babel}
\usepackage{geometry}
\geometry{top=2.5cm, bottom=2.5cm, left=3cm, right=2.5cm}
\usepackage{setspace}
\onehalfspacing
\usepackage{hyperref}
\hypersetup{colorlinks=true, linkcolor=blue, urlcolor=blue}
\usepackage{booktabs}
\usepackage{longtable}
\usepackage{microtype}
\usepackage{parskip}

\title{\textbf{ {!! $projekt->titel !!} }}
\author{Systematisches Literaturreview}
\date{\today}

\begin{document}

\maketitle
\tableofcontents
\newpage

@foreach ($sections as $section)
\section{ {!! $section['title'] !!} }
{!! $section['content'] !!}

@endforeach

\end{document}
