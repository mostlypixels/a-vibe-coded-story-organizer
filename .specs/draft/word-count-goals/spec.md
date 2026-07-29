---
status: draft
---

# Word Count Goals

Save a daily word count per project.

- One row: project, date, total words.
- Write it on save, not at midnight.
- A day is the writer's day, not the server's.
- Deleting counts as negative.
- No history before you turn it on.

The project has configurable word count goals which can be modified and are not historicized:
- daily goal
- monthly goal
- Total goal

There is a graphical representation of the word count on the project dashboard:

- It's a horizontal line chart. (chart.js).
    - X axis shows the dates.
    - Y axis shows the total words.
    - A color line for the word count.
    - A grey line for the daily word goal (which is the same every day)
- the current month is the default range, from the beginning of the month to the end of the month.
- you can select a different range: by month or by period
- The points' labels shows the total words for the day (for the word count line), or the daily goal (for the daily goal line).
