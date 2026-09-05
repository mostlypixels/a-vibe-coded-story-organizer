# Driving the app yourself

The app is already running at `http://localhost:8000`. Your caller started it. You only
look around inside it.

Your hands are a small browser driver. The driver is not part of the app, so leave it out
of your feedback. Judge only what you see on the page.

## Log in

Run this from the repo root. Use one session name for your whole visit, so you stay logged in.

    cd .claude/skills/run-imagoldfish
    node driver.mjs --session <your-name> <<'END'
    nav http://localhost:8000/login
    wait-for input[name=email]
    fill input[name=email] admin@example.com
    fill input[name=password] password
    click button[type=submit]
    wait-for text=Dashboard
    screenshot dashboard
    END

## Commands

| command | what it does |
|---|---|
| `nav <url>` | go to a page |
| `resize <w> <h>` | change the window size. `resize 390 844` is a phone |
| `wait-for text=<t>` / `wait-for <css>` | wait for text or an element |
| `click <css>` | click |
| `fill <css> <value>` | type into a field |
| `type <text>` | type into whatever has focus |
| `press <key>` | press a key |
| `screenshot [name]` | take a picture of the page |
| `screenshot-element <css> [name]` | take a picture of one part |
| `text-content <css>` | print the words in an element |

## Look at the picture

Screenshots land in `.claude/skills/run-imagoldfish/chromium_cli/sessions/<session>/screenshots/`.
Open each one with the Read tool and look at it. A driver command can print "ok" while the
page shows an error.

## Rules

- Go where your character would go. Follow your own curiosity, not a script.
- Take a screenshot at every screen you judge.
- When a page confuses you, say so and stay confused. Work it out on the page, not in the source.
- Broken demo data is acceptable. Say what you changed or broke.
- Run the driver and nothing else. When the app does not answer, stop and say so.
