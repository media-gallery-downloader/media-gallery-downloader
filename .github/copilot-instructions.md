# GitHub Copilot Instructions

## Prompt Logging

When working on this project, log all user prompts to `.prompts/YYYY-MM-DD.md` files.

### Format for each prompt entry:

```markdown
---

### Prompt N

**Model:** [The AI model being used, e.g., "Claude Opus 4.5 (Preview)", "GPT-4o", etc.]

**Context:** [Describe any context: open files, pasted images, error messages, etc. Use "None" if no special context]

> [User's exact prompt/request]
```

### Guidelines:

1. Create a new dated file if one doesn't exist for today
2. Number prompts sequentially within each day
3. Always include the model name (e.g., "Claude Opus 4.5 (Preview)")
4. Include context such as:
    - Open/active files
    - Pasted images or screenshots (describe what they show)
    - Error messages or logs
    - Previous conversation context if relevant
5. Log the user's request verbatim in a blockquote
6. Do NOT log AI responses, only user prompts
7. Update the log BEFORE executing the user's request

### File structure:

```
.prompts/
├── README.md           # Explains the prompt logging system
└── YYYY-MM-DD.md       # Daily log files
```
