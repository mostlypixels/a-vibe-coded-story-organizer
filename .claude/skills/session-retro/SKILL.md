---
name: session-retro
description: Reflect on the current session's actual work — repeated manual loops, corrections, one-off agents/prompts — and propose generalizable, reusable skills or agents grounded in what actually happened, not hypotheticals. Use when the user asks what to generalize from this session, what tooling to build next, or to suggest skills/agents based on how a task just went.
---

# session-retro

Turn a session's actual toil into concrete, buildable proposals for reusable
`.claude/skills/` and `.claude/agents/` — instead of generic advice.

## Steps

1. **Review this conversation for concrete repetition**, not abstract possibility:
   places you (a) did the same multi-step thing by hand more than once, (b) had to
   manually recap state to a fresh agent/subagent because it had no memory of prior
   steps, (c) got corrected by the user on approach, or (d) did a manual confirm/cleanup
   step at the end that could be a standing offer instead. Ground every proposal in a
   specific moment — cite what happened, not "it might help to...".

2. **Check what already exists** before proposing anything: list `.claude/agents/` and
   `.claude/skills/` (and any project skills referenced in the system prompt) so you
   don't suggest rebuilding something that's already there, and so new proposals
   compose with existing ones rather than duplicating them.

3. **Propose a short list** (favor 2–4 over an exhaustive catalog) of candidate
   skills/agents. For each: name it, describe what it would do in one or two
   sentences, and name the specific moment(s) in the session that motivate it. Order by
   how much repeated toil it would have saved tonight, not by novelty.

4. **Ask the user which to build now**, via `AskUserQuestion` with `multiSelect: true`
   — don't assume they want all of them, and don't build anything before they choose.

5. **For selected proposals, write the files** using this repo's existing conventions
   as templates (check `.claude/skills/*/SKILL.md` and `.claude/agents/*.md` for the
   current frontmatter shape and tone — follow whatever's actually there rather than a
   fixed template, since conventions may have evolved). Prefer plan mode for anything
   beyond a trivial single file, so the user can review scope before files land.

## Notes

- This skill is about *this session's* concrete experience. If the user wants a broader
  audit unrelated to what just happened, that's a different, more open-ended task — say
  so rather than forcing today's specifics into it.
- Don't propose a skill/agent that only fits the specific feature just built (e.g.
  something Codex-specific) unless the user asks for that — the point is generalizing
  past the one-off.
