# Open questions

1. **Hover timing?** Recommend: open at once, close 250 ms after the pointer leaves.
2. **Does focus on the link open the menu?** Recommend: no. Keyboard users open it with the chevron. Otherwise every Tab across the nav opens menus.
3. **Does a hover menu close when another one opens?** Recommend: yes, only one menu is open at a time. It follows because the pointer left the first item.
4. **The user menu on error pages** (`error-navigation.blade.php`)? Recommend: same change, one component.
5. **Chevron name?** Recommend: "Story menu", "Codex menu"… and "Account menu" for the user item.
6. **Account page name and URL?** Recommend: "Account", `/account`.
7. **Escape for every `x-dropdown`, not only hover ones?** Recommend: yes. It is an accessibility fix, and no dropdown uses Escape now.
