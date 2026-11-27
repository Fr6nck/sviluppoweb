# Email welcomemyhouse.it — da attivare su Hostinger

## Casella vera (1)
- **assistenza@welcomemyhouse.it**
  Password forte. Servono i dati SMTP (server, porta, cifratura SSL/TLS, utente) da consegnare a Massimiliano.

## Alias, solo inoltro verso assistenza@welcomemyhouse.it (3)
- **commerciale@welcomemyhouse.it** — informazioni MyHouse siti, preventivi
- **news@welcomemyhouse.it** — newsletter e campagne (mittente su Brevo)
- **amministrazione@welcomemyhouse.it** — fatture, ricevute, pagamenti

## DNS del dominio welcomemyhouse.it
- **SPF**: record TXT di Hostinger per l'invio della posta
- **DKIM**: attivare dal pannello email di Hostinger e pubblicare il record
- **DMARC**: record TXT `_dmarc.welcomemyhouse.it` → `v=DMARC1; p=none`
- (Più avanti, per la newsletter: record DKIM di Brevo)

## Da consegnare dopo l'attivazione
- Password di assistenza@
- Dati SMTP di assistenza@
- Conferma che i 3 alias inoltrano correttamente (mandare una mail di prova a ciascuno)
