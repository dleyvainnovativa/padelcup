1. Setup & Auth

 Log in as a manager
 Create a tournament (name, dates starts_on/ends_on, play window play_start/play_end, match duration)
 Edit the tournament; confirm changes persist
 Toggle is_listed on/off
 Verify slug is generated and the URL works

2. Categories (all formats)
Create one category of each format so you test every path:

 Round-robin only
 Elimination only
 Groups + elimination (hybrid) with advance_per_group and extra_qualifiers set
 Mexicano (group_format = mexicano, cross pairing)
 Mexicano (classic pairing)
 Edit a category and change its format → confirm it saves (the $fillable fix)
 Set price, min/max pairs, tint color

3. Pairs & Import

 Add pairs manually
 Import pairs via CSV
 Import with a duplicate player → confirm the dedupe defaults to linking the matched player
 Choose "Crear nuevo" on a dupe → confirm it creates a separate player
 Verify the category pair count matches between the category page and the tournament overview (the 38-vs-41 fix)
 Delete a pair; confirm count updates everywhere

4. Groups

 Generate groups for a round-robin category → verify match count (4-pair = 6 matches)
 Generate groups for a Mexicano category → verify 4-pair group = 4 matches (not 6)
 Check group sizing logic (3/4/5, prefers 4)
 Verify shared-player separation (a player in 2 pairs → different groups)
 Drag/tap a pair between groups (desktop drag, mobile tap) → only affected groups' matches rebuild
 Late-register a pair → it lands in "Sin asignar", existing arrangement untouched
 Move a pair from "Sin asignar" into a group
 Hit "Regenerar" → confirm the warning fires, and it wipes the manual arrangement only on confirm
 Switch the Acomodar/Posiciones tabs

5. Results entry (the core flow)

 Enter a score on the results page → confirm → tournament locks (phase → En curso)
 Verify regeneration is now blocked post-lock
 Enter an invalid score (tied set, or 2-2 sets) → validation error
 Edit a confirmed result → confirm it re-propagates
 Record a walkover → loser gets 0 pts, conventional 6-3 6-3 shows
 Test Mexicano R1 → R2 propagation: confirm both R1 matches → R2 cross pairings fill in (winner A vs loser B) → no longer "Por definir"
 Verify Mexicano standings rank by games won → sets → points
 Check the round-robin mini-table tiebreaker (create a 3-way points tie)

6. Brackets / Elimination

 Generate a bracket for a hybrid category → verify qualifiers (top N per group + extras)
 Confirm a bracket match → winner advances to the next round
 Edit a confirmed bracket result so the other pair wins → downstream match updates, stale results clear
 Verify round labels show F / SF / QF / 8F (not R1/R2/R3)
 Tap a bracket match card → enter scores via the sheet → card turns green, winner advances
 Test a cross-group tie that needs manual resolution → "Resolver empate"
 Cross-navigate: Brackets → Resultados button, Resultados → Llave button

7. Scheduling

 Add venue → add courts → verify availability auto-derives from the play window
 Delete a court (confirm dialog fires)
 Change the tournament's play hours/duration → confirm only out-of-window matches get unscheduled, with the warning + count message
 Timezone check: place a match at a specific time → reload → confirm it shows the same time (no 6-hour shift)
 Drag a match onto a court/time (desktop)
 Tap an empty cell (mobile) → bottom sheet → assign a match
 Schedule a Mexicano R2 placeholder (no pairs yet) → shows "Ganador/Perdedor" labels
 Switch days → place a match → confirm it stays on that day (doesn't jump to day 1)
 Player conflict: schedule two matches sharing a player at the same time → conflict warning + force option
 Auto-programar → fills empty slots, reports placed/unplaced
 Tap a scheduled match → control sheet → enter scores inline → status color updates (green=played, amber=playing)
 Quitar de la programación → match returns to the tray

8. Resumen (summary page)

 Open Resumen from the tournament page
 Switch categories via the dropdown
 Grupos inner tab: per-group standings, top-3 highlighted, qualifiers marked with ↑
 General inner tab: combined ranking, Grupo column, sorted by group-position → points
 Finish a bracket → General tab shows the Podio callout (real champion/runner-up/3rd)
 Before bracket finishes → General shows the snapshot top-3 highlight instead
 Verify joint-3rd shows both semifinalists when no 3rd-place match
 Header buttons: Calendario, Ver torneo

9. Payments (if testing Stripe)

 Connect a Stripe account (Cobros)
 Self-registration → checkout → payment recorded
 Pay-both combined checkout (one session, 2 line items)
 Invite flow: registrant pays half → partner completes via quick-register link
 Refund a payment → platform fee preserved
 Check the Casos (issues) queue for stuck registrations

10. Responsive / cross-cutting

 Test each major page on mobile (≤600px): category show buttons wrap, tables scroll horizontally, results match-rows stack, score inputs stack one-per-row
 Light/dark theme toggle → active tabs show accent, everything readable
 Themed confirm modals (not native alerts) on all destructive actions
 Cancel button closes the modal
 Breadcrumbs navigate correctly on nested pages