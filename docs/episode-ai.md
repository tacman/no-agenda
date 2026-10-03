# Episode AI pilot

The public site retains its original stylesheet and `app` importmap entry. The AI Lab uses Tabler and an independent `admin` entry. The gear-adjacent admin icon opens `/admin/episodes`; search cards link directly to `/admin/episodes/{code}` (`episode_show`). There is no duplicate episode list.

The lab and read-only workflow views are public for this pilot. Existing mutation routes remain behind the admin access rule. Published on 2026-10-03 at https://no-agenda.survos.com/admin/episodes/1907 (application release e7253e1). Episode 1907 has its existing episode summary and 29 segment summaries; 28 are from Mistral and one retains its prior Ollama result with a pending retry. No new AI jobs were run for publication. Summary/segment and claim provenance rows were transferred transactionally, resolving the episode by code and remapping local ID 243 to production ID 241. Production batch job IDs were left unset because provider job records were not imported. RAG, diarization, and an embedded admin audio player remain deferred.

## Workflow

`src/Workflow/EpisodeFlow.php` declares the state-bundle attributes:

- `new` → async `prepare` → `ai_ready`
- `ai_ready` → async `ai_task` → `ai_ready` while tasks remain
- `ai_ready` → async `ai_done` → `complete` when the queue empties

The state-bundle postPersist/postFlush listener queues the initial transition. Place metadata owns `next`; do not also put the same cascade on transitions.

Segments follow `ai_ready → ai_task → ai_ready` until their dynamic task queue empties, then `ai_done → complete`. A segment completion listener flushes the result and sends the parent an ordinary TransitionMessage. The episode's readiness guard blocks synthesis until every segment is complete and has a summary. Duplicate wakeups after completion are no-ops because the transition is no longer enabled. Start a single local worker for Ollama to avoid competing model calls.

Episode uses the existing `MarkingTrait` and `PendingStepsTrait`. Each `EpisodeSegment` is a Doctrine workflow subject with its own marking, pending steps, source metadata and dense summary. The former `EpisodeAnalysis` JSON/cursor store is migrated into those rows. Episode preparation persists segments; state-bundle postPersist/postFlush automatically dispatches their first AI transition. The new episode summary task uses the existing ai-workflow `Subject`, `AbstractPromptTask`, and `TaskRunner::runNext()`. The app supplies text through the Subject context and projects the resulting dense-summary claim. No new shared runner or subject abstractions are required.

TaskRunner records timing, model, token usage, prompts and responses in ClaimRun. Each transcript segment has a distinct subject identifier (`episode-id:segment-content-id`). The runner catches task failures; the app keeps the durable episode queue unchanged and throws if no successful run was recorded. Check the logs and failure claims before retrying.

Episode preparation uses the existing chapter and transcript crawlers to download missing source files from the episode feed URLs, then persists the segments. Missing/unavailable transcripts fail preparation for retry; publication notifications are not part of this AI flow. Publisher chapter starts/titles define logical boundaries when available. Long chapters split into approximately 6,000-character segments without cutting captions; a caption crossing a chapter boundary stays in the chapter where it starts. Unchaptered material has a null chapter identity, not an invented topic label.

Each persisted segment retains its stable content ID, source text hash, chapter ID/title/start/provenance, caption indices and audio timestamps alongside the summary. These are the source units for later retrieval and embeddings (not implemented yet). Existing state-bundle TransitionMessages identify the individual EpisodeSegment by its Doctrine ID. Its transition supplies source text, stable segment identity and chapter context to the existing ai-workflow Subject. No app-specific message bus or bundle changes were added. Final synthesis receives the completed summaries with chapter titles and time ranges. Separate chapter-level synthesis can be added later; currently long chapters have multiple segment summaries.

A failed rerun cannot reuse the prior successful ClaimRun: projection requires a newly recorded run.

## Debugging

Use existing state-bundle commands; no separate AI script:

```sh
php bin/console state:iterate Episode -m new -t prepare --sync --cascade=none --limit=1 -vv
php bin/console state:iterate EpisodeSegment -m ai_ready -t ai_task --sync --cascade=none --limit=1 -vv
```

`--cascade=none` is deliberate for a single bounded debugging step. Without it, follow-up transitions can run or queue according to cascade configuration. Normal async transports are `episode.prepare`, `episode.segment.ai.task`, `episode.segment.ai.done`, `episode.ai.task` and `episode.ai.done`; inspect `state:queues:dump` / `messenger:stats` before starting a worker. The local pilot contains only episode 1907. Inspect segment markings/pending steps for progress; chapter-aware preparation produces 29 segments plus one episode synthesis task.

Ollama is configured with `OLLAMA_HOST_URL` and `OLLAMA_TEXT_MODEL` (currently `ministral-3:14b`). The initial local pilot used no paid model. Its 29 chapter-aware segments (26,458 source words) took 442.6 seconds of inference, with 39,729 input and 6,156 output tokens; episode synthesis is additional.

The review page renders summaries through `markdown_to_html`, with raw HTML and unsafe links disabled. Source captions remain collapsed and render as timestamped rows. Open/close-all controls target summary panels only.

## Local bundle fix

The explicitly requested optional-dependency fix lives in the mono ai-workflow bundle source and is mirrored into this checkout's vendor copy for local verification. Missing agents/platforms are nullable; running an unconfigured task raises a clear exception. OptionalTaskDependencyTest covers this behavior. The application now locks the published 2.34.31 release containing this fix; deployment no longer depends on a patched vendor copy. No other shared bundle changes are retained.

## M4 tunnel

Public base URL: `https://m4-ollama.survos.org`; OpenAI-compatible base: `https://m4-ollama.survos.org/v1`.

The existing mac-depot Cloudflare tunnel forwards this hostname to a loopback-only Caddy proxy on port 11435, which validates a bearer token and forwards allowed inference/model-discovery routes to Ollama on port 11434. Model-management routes are not exposed. TLS terminates at Cloudflare; the tunnel is encrypted. Streaming is forwarded without response buffering. This is a direct request/response API, with no callback service.

Machine configuration:
- `~/.cloudflared/mac-depot.yml`: ingress hostname.
- `~/.cloudflared/m4-ollama.Caddyfile`: protected proxy configuration (mode 600).
- `~/.cloudflared/m4-ollama.token`: bearer token (mode 600; never commit).
- `~/Library/LaunchAgents/com.survos.m4-ollama-proxy.plist`: starts Caddy at login and restarts it after exit.

Set `OLLAMA_HOST_URL=https://m4-ollama.survos.org`, `OLLAMA_API_KEY` to the token, and `OLLAMA_TEXT_MODEL=ministral-3:14b`. These are currently in `.env.local`; tracked defaults retain direct loopback access with no key. Symfony's Ollama bridge uses native `/api/chat`; other sites can use `/v1/chat/completions` with the same bearer token. Both APIs are allowed.

The app's scoped HTTP client has a 90-second idle timeout and a 110-second total limit per request. These do not override Cloudflare edge limits. Keep work bounded and run it in Messenger; production workers need the episode transports configured before deployment. A sleeping/offline Mac makes this endpoint unavailable; standard Messenger retries/failure handling apply.

Verified through the public hostname: authenticated `/v1/models` returns 13 models, unauthenticated discovery returns 401, and a tiny `/v1/chat/completions` request returns 200. Its 9.33-second elapsed time was a connectivity test, not a transcript throughput benchmark.

DNS for `survos.org` was configured in Cloudflare's dashboard. The saved `cloudflared` login is scoped to `scanstationai.work`; do not use that certificate's `tunnel route dns` for survos.org (it appends the wrong zone). The two mistakenly created records were removed.

For a complete synchronous debug pass over the local episode's pending segments:

```sh
php bin/console state:iterate EpisodeSegment -m ai_ready -t ai_task --sync --cascade=sync --limit=30 -v
```

The app declares the standard Messenger `sync://` transport so state-bundle's synchronous cascades work. Production should consume the async episode and segment queues. The integration test verifies segment flush creates its queued message and the parent remains blocked until a completed summary exists.


## Provider batches (Mistral Small 4)

`survos/ai-batch-bundle` supplies the Mistral client, `AiBatch` entity, messages and
`PollBatchesTask` scheduler (every two minutes). The app follows Mediary's
`AssetAiBatchSubmitter`/poll/apply pattern; no shared bundle was modified.
Symfony AI's native batch path currently used by the bundle is OpenAI; Mistral
uses its existing `MistralBatchClient`.

`EpisodeSegmentFlow::ai_task` declares `#[Transition(batch: 29)]` for this pilot.
Enable grouping with `EPISODE_AI_BATCH=1` when dispatching and consuming. The
tracked default is off, so ordinary local debugging remains on Ollama.
`EpisodeSummaryTask` uses the existing AbstractPromptTask batch request/parser
helpers and the same prompt/schema as local inference. Its batch provider is
Mistral, model `mistral-small-2603` (Mistral Small 4). Set `MISTRAL_API_KEY` locally.

For already prepared pending segments of one episode (the filter uses the DB ID):

```sh
EPISODE_AI_BATCH=1 php bin/console state:iterate EpisodeSegment -m ai_ready -t ai_task --filter='episode=243' --limit=29 --cascade=none
EPISODE_AI_BATCH=1 php bin/console messenger:consume episode.segment.ai.task --fetch-size=29 --time-limit=30 --failure-limit=1 -v
php bin/console messenger:consume scheduler_default -v
```

One worker collects requests up to the attribute limit (or flushes on idle),
submits a provider job and locks segments. Stop that worker after submission;
the scheduler can run next on its own. No concurrent AI workers are needed.
The size controls requests per provider job, not total episodes selected.

The poll handler saves raw JSONL to `var/ai-batch/<id>.jsonl`, verifies SHA-256,
then dispatches the bundle's ApplyBatchResultsMessage. The applier uses the task's
shared result parser and ClaimIngestor. Each result is recorded as
`episode_summary@batch-<id>` so it does not replace the original Ollama run.
Successful results update the segment review page and finish its workflow.
Failures retain the old summary and pending task, unlock the segment, and record
an error in AiBatch.meta; no automatic sync fallback or paid resubmission occurs.
An applied batch is idempotent. The raw archive and request metadata support
inspection; this is not a cross-request response cache. Preserve `var/ai-batch`
as durable storage when deploying (or adopt Mediary's S3 archive).

Completed episodes are not reopened automatically during this comparison. Their
existing synthesis remains unchanged; only the requested segment summaries are
replaced. New episodes awaiting synthesis are woken by normal segment completion.
Do not interpret provider batch turnaround as per-request inference duration;
the UI labels batch runs accordingly.


### First Mistral batch result (2026-10-02)

Episode 1907: one provider batch of 29 segments, collected approximately 21 minutes
28 seconds after submission. Mistral reported 29 completed requests, but the
shared task parser correctly rejected one truncated response (`finish_reason:
error`). 28 summaries were applied. Segment `2c712241ffb36ab9570342c5` retains its
previous local summary and pending task for explicit retry. No automatic retry
was submitted. Archived usage totals: 40,193 input / 5,407 output tokens,
including the incomplete response. Provider completion counts alone are not
sufficient to declare every application result valid.
