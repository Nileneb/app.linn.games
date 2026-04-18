{!! '%' !!} Generiert von app.linn.games — IEEE Format
\documentclass[conference,a4paper]{IEEEtran}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage{hyperref}
\usepackage{booktabs}
\usepackage{longtable}
\IEEEoverridecommandlockouts

\begin{document}

\title{ {!! $projekt->titel !!} }
\maketitle

@foreach ($sections as $section)
\section{\uppercase{ {!! $section['title'] !!} }}
{!! $section['content'] !!}

@endforeach

\end{document}
