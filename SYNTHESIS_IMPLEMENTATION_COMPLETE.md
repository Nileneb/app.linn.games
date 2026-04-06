# Synthesis Markdown Implementation – COMPLETE

## What's Done ✅

### 1. System Prompt (``.claude/REVIEW_AGENT_SYSTEM_PROMPT.md`)
A comprehensive German system prompt for the Review Agent (P5-P8) that explains:
- **RAG Pipeline**: Automatic download flow (DownloadPaperJob → IngestPaperJob → paper_embeddings)
- **Where Chunks Are**: Prepended to agent context as `=== RELEVANTE DOKUMENT-ABSCHNITTE (Embedding-Retriever) ===`
- **Forbidden Fields**: Explicitly lists `retrieval_*` fields that must NOT be touched
- **Phase-Specific Tasks**: Detailed Input/Task/Output for P5, P6, P7, P8
- **Markdown Synthesis Format**: Example structure with HTML comment traceability

### 2. SynthesisMarkdownService (`app/Services/SynthesisMarkdownService.php`)
Generates phase-specific markdown files with complete source attribution:

**P5 Screening**
- Inclusion/Exclusion criteria
- Screening decisions per paper
- Relevant excerpts from chunks
- HTML comments: `<!-- paper_id: ...; chunk_index: ...; similarity: 0.xx -->`

**P6 Quality Assessment**
- Study type classification
- Risk of Bias tool & evaluation
- Evidence from full text
- Traceability comments

**P7 Data Extraction**
- Sample characteristics (N, country, intervention)
- Findings & outcomes
- Pattern analysis
- Source-attributed quotes

**P8 Documentation**
- Search protocol (databases, strings)
- Limitations
- Reproducibility checklist
- Metadata footer (papers referenced, chunks cited, avg similarity)

### 3. ProcessPhaseAgentJob Integration (`app/Jobs/ProcessPhaseAgentJob.php`)
Enhanced to capture and use retrieval chunks:

**Flow:**
```
1. prependRetrieverContext() stores chunks in $retrievedChunks
2. Agent executes with chunks in context
3. enrichResponseWithSynthesis() parses agent JSON
4. SynthesisMarkdownService generates markdown
5. Embeds markdown in result.data.md_files[]
6. LangdockArtifactService persists all files
```

**Chunk Capture:**
- Stored from RetrieverService output (paper_id, chunk_index, text_chunk, similarity)
- Grouped by paper for synthesis organization

**Response Enhancement:**
- Supports both `meta/result` and flat JSON structures
- Safely embeds synthesis markdown without overwriting agent data
- Falls back gracefully if parsing fails

**Artifact Storage:**
- Synthesis file: `langdock/artifacts/projekte/{id}/phasen/p{phase}/review_agent/synthesis_p{phase}.md`
- JSON envelope: `.../{timestamp}-p{phase}-review_agent.json`
- Also available via display_content in UI

---

## Traceability Format

Every quote in synthesis markdown includes HTML comments:

```markdown
> The study included 250 participants aged 18-65 years.
<!-- paper_id: 550e8400-e29b-41d4-a716-446655440000; chunk_index: 5; similarity: 0.91; source: Abstract -->
```

**To trace back:**
1. Note the `paper_id` from HTML comment
2. Query DB: `SELECT * FROM p5_treffer WHERE id = '{paper_id}'`
3. Get `retrieval_storage_path` → read PDF
4. Locate chunk via `chunk_index` from paper_embeddings table
5. Cross-check with `similarity` score (0.00–1.00)

**Footer metadata:**
- Unique papers referenced
- Total chunks cited
- Average similarity score
- Generation timestamp

---

## What You Need to Do (Manual Step)

### Update Review Agent in Langdock UI

1. **Open Langdock dashboard**
2. **Find agent:** `8548c68a-4fd7-45bc-8d88-ce82b2891f34` (Review & Synthesis Agent)
3. **Edit settings:**
   - Click "Settings" or pencil icon
   - Scroll to "System Prompt" field
   - **Clear existing text**
   - **Copy entire content from** `.claude/REVIEW_AGENT_SYSTEM_PROMPT.md`
   - **Paste it into the field**
4. **Save agent**
5. **Test:** Run P6 again – agent should now use RAG chunks, NOT attempt downloads

---

## Testing Checklist

After updating the Review Agent:

- [ ] **No download attempts**: Verify agent does NOT set `retrieval_downloaded` or `retrieval_status`
- [ ] **RAG usage**: Check logs for "Retriever: N Chunks für Phase-Agent vorbereitet"
- [ ] **Synthesis file created**: Verify `synthesis_p6.md` (or p{phase}.md) exists in artifacts
- [ ] **HTML comments present**: Open markdown file, check for `<!-- paper_id: ... -->`
- [ ] **Correct structure**: Verify markdown follows phase-specific format (headers, tables, etc.)
- [ ] **Full text referenced**: Ensure citations use chunks from context, not abstracts

---

## File Locations

**Code:**
- System prompt template: `.claude/REVIEW_AGENT_SYSTEM_PROMPT.md`
- Service: `app/Services/SynthesisMarkdownService.php`
- Job: `app/Jobs/ProcessPhaseAgentJob.php`

**Generated artifacts** (for each project):
```
storage/langdock/artifacts/
└── projekte/{projekt-id}/
    └── phasen/
        ├── p5/
        │   └── review_agent/
        │       ├── {timestamp}-p5-review_agent.json (structured)
        │       └── synthesis_p5.md (markdown)
        ├── p6/
        │   └── review_agent/
        │       ├── {timestamp}-p6-review_agent.json
        │       └── synthesis_p6.md
        └── [p7, p8 similar]
```

---

## How Synthesis Generation Works

```php
// In ProcessPhaseAgentJob::handle()

// 1. Retrieve chunks (happens in prependRetrieverContext)
$chunks = app(RetrieverService::class)->retrieve($query, $projektId);
// Returns: [{paper_id, title, chunk_index, text_chunk, similarity}, ...]

// 2. Chunks prepended to agent context
$contextText = $retriever->formatAsContext($chunks);
// Agent sees chunks as "=== RELEVANTE DOKUMENT-ABSCHNITTE ==="

// 3. Agent responds with JSON (e.g., P6 quality assessments)
$response = app(SendAgentMessage::class)->execute(...);

// 4. Synthesis generation
$synthesisMarkdown = app(SynthesisMarkdownService::class)->generateSynthesis(
    phaseNr: 6,
    agentData: $response['data'],  // Parsed JSON
    retrievedChunks: $chunks       // Original chunks with metadata
);

// 5. Embed in response for persistence
$response['result']['data']['md_files'][] = [
    'path' => 'synthesis_p6.md',
    'content' => $synthesisMarkdown
];

// 6. Artifact service extracts and stores
app(LangdockArtifactService::class)->persistFromAgentResponse(
    jsonWithMdFiles: $response,
    options: [
        'scope' => 'phase',
        'phase_nr' => 6,
        'config_key' => 'review_agent',
    ]
);
// Creates: synthesis_p6.md with HTML comment traceability
```

---

## Benefits

✅ **Full Reproducibility**: Every quote attributed to specific chunk (paper_id + index + similarity)
✅ **Automatic Generation**: Synthesis created as part of normal agent execution, no extra steps
✅ **Phase-Specific**: Each phase has appropriate markdown structure (P5=screening, P6=QA, etc.)
✅ **Graceful Degradation**: Falls back to original response if synthesis generation fails
✅ **Integrated Storage**: Automatically persisted via existing artifact system
✅ **Metadata Audit Trail**: Footer includes generation timestamp, chunk count, avg similarity

---

## Next: Verify RAG Pipeline

After the agent update, run a test to confirm:

```bash
# Run P6 with retriever enabled
docker compose exec php-cli php artisan tinker
# Then test a phase execution...
```

Or simply:
1. Open recherche project
2. Click P6 button
3. Wait for agent to complete
4. Check logs:
   - Should see: "Retriever: 5 Chunks für Phase-Agent vorbereitet"
   - Should NOT see: "retrieval_downloaded" or "retrieval_status" updates
5. Download generated synthesis_p6.md
6. Verify HTML comments with paper_id, chunk_index, similarity

---

## Summary

- **Synthesis Markdown Service**: Phase-specific generation with embedded traceability
- **ProcessPhaseAgentJob**: Integrated chunk capture and response enhancement
- **System Prompt**: Comprehensive guide explaining RAG pipeline to agent
- **Status**: Code complete, awaiting manual Review Agent update in Langdock UI

Everything is ready. Just copy-paste the system prompt into Langdock and test! 🚀
