# GitHub Copilot Instructions

## Prompt Logging

When working on this project, log all user prompts to `.prompts/YYYY-MM-DD.md` files.

### Format for each prompt entry:

```markdown
---

### Prompt N

**Context:** [Describe any context: open files, pasted images, error messages, etc. Use "None" if no special context]

> [User's exact prompt/request]
```

### Guidelines:

1. Create a new dated file if one doesn't exist for today
2. Number prompts sequentially within each day
3. Include context such as:
   - Open/active files
   - Pasted images or screenshots (describe what they show)
   - Error messages or logs
   - Previous conversation context if relevant
4. Log the user's request verbatim in a blockquote
5. Do NOT log AI responses, only user prompts
6. Update the log BEFORE executing the user's request

### File structure:

```
.prompts/
├── README.md           # Explains the prompt logging system
└── YYYY-MM-DD.md       # Daily log files
```
