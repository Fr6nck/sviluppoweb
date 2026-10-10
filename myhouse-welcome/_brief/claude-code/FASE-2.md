# Fase 2 di 5 — Flusso: il piano una sola volta, procedura in 5 passi
Modello consigliato: Sonnet 5.5 · Una migrazione (`007`).

Leggi `CLAUDE.md`. La Fase 1 è già fatta: parti da quel codice. Riferimento visivo: U1, R1, U2, U3 in `_brief/revisione/revisione.html`.

File da leggere: `app/src/routes_public.php` (rotte `/registrati`, `/accedi`, `/piano`), `app/src/routes_host.php` (costante `MHW_PASSI`, `/pannello/nuova`, `/procedura/{passo}`), `app/src/Auth.php`, `app/src/Plans.php`, `app/views/auth/register.php`, `app/views/pub/plan.php`, `app/views/host/wizard.php`, `app/views/host/new_property.php`, `app/views/layout/cms.php`.

## Da fare

1. **U1 · Il piano non si sceglie due volte.**
   - Chi arriva a `/registrati?piano=N` (anche con `&strutture=Q`): dopo la registrazione il piano si salva su `accounts.intended_package_version_id` e `intended_quantity`, come fa oggi il POST di `/piano`, poi si va **direttamente** a `/pannello/nuova`.
   - Lì compare un avviso «Piano {nome} scelto · non paghi adesso · Cambia».
   - `/piano` resta solo per chi si registra senza piano o preme «Cambia».
   - Nella pagina `/piano`: card intera cliccabile, senza la riga che ripete nome e prezzo; nel Portfolio il selettore di quantità sta dentro la card, allineato come le altre.

2. **R1 · Registrazione.**
   - In alto un chip con il piano scelto e il link «cambia».
   - Campi: «Nome e cognome», Email, Password con pulsante «Mostra» e barra di robustezza.
   - **Una sola** casella obbligatoria: «Accetto i Termini e condizioni».
   - La privacy diventa una riga sotto il pulsante: «Creando l'account dichiari di aver letto l'informativa privacy», con il link.
   - `Auth::recordConsent` continua a salvare versione e data di entrambi i documenti.
   - Commento nel codice: formulazione da far verificare al consulente privacy.

3. **U2 · Una sola navigazione durante la procedura.**
   - Finché la struttura non è mai stata pubblicata e l'host è in `/pannello/{id}/procedura/…`, nascondi le tab della struttura (Contenuti, Lingue, Aspetto, QR & Link, Statistiche, Impostazioni).
   - Restano visibili solo i passi e «Esci, continuo dopo».
   - Il banner di verifica email diventa una riga compatta sopra i passi.

4. **U3 · Da 7 a 5 passi, lingue alla fine.** Nuovo `MHW_PASSI`:

   | Chiave | Titolo | Contenuto |
   |---|---|---|
   | `struttura` | Struttura e contatti | quello di oggi + **«In che lingua scrivi la guida?»** (una scelta, italiano preselezionato, salva `properties.default_locale`) |
   | `arrivo` | Arrivo e partenza | il vecchio passo `checkin` |
   | `sezioni` | Sezioni | scelta e compilazione insieme: «Aggiungi» attiva la sezione e apre subito il suo editor sotto la card (stesso form di `section.php`, caricato nella pagina) |
   | `aspetto` | Aspetto | il vecchio passo `aspetto` |
   | `pubblica` | Anteprima e pubblica | il vecchio passo `anteprima` + in fondo un riquadro **facoltativo** «Vuoi la guida anche in altre lingue?» con le lingue consentite dal piano |

   - **Le traduzioni non bloccano mai la pubblicazione.** `Guide::problems()` non deve segnalare lingue incomplete come errore.
   - Nella tab Lingue: percentuale per lingua («English 60%») e campi mancanti evidenziati.
   - Nella guida ospite, un campo non tradotto mostra il testo della lingua principale. Verifica che succeda già, altrimenti correggi `Guide::normalize`.
   - **Migrazione `007_passi_procedura.php`**: converte `properties.wizard_step` con questa mappa:
     - `checkin` → `arrivo`
     - `contenuti` → `sezioni`
     - `lingue` → `aspetto`
     - `anteprima` → `pubblica`
     - `struttura`, `sezioni`, `aspetto`, `fatto`: invariati
   - I vecchi URL `/pannello/{id}/procedura/{checkin|contenuti|lingue|anteprima}` devono fare un redirect 301 al passo nuovo corrispondente.
   - Aggiorna anche `Properties.php`, `Demo.php` e la rotta `/pannello/nuova`, che oggi scrivono `checkin` e `sezioni`.

## Verifica prima di consegnare
- Da landing «Crea gratis con Plus» → registrazione → nome struttura: la pagina dei piani non compare mai.
- Da «Crea gratis» generico si passa da `/piano`.
- Una struttura creata con la v1 ferma a `contenuti` si riapre al passo `sezioni` senza errori.
- Pubblicazione possibile con la sola lingua principale.
- La migrazione gira su un database SQLite vuoto e su uno con i dati demo; controlla che la sintassi valga anche per MySQL.

Consegna `welcomebook-v2-fase2.zip` come da `CLAUDE.md` e fermati.
