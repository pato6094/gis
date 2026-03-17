PIATTAFORMA PUNTI AFFILIAZIONE - INSTALLAZIONE RAPIDA

1) Carica TUTTI i file direttamente dentro public_html.
2) Crea un database MySQL da cPanel.
3) Importa il file schema.sql in phpMyAdmin.
4) Apri config.php e inserisci host, nome database, utente e password.
5) Vai sul dominio e registra il primo account: diventerà admin automatico.
6) Entra in Admin e controlla:
   - affiliate_tag
   - categoria predefinita
   - commissioni per categoria
   - quota utente per categoria
   - premi riscattabili

COME FUNZIONA ORA
- L'utente seleziona la categoria del prodotto.
- Incolla un link Amazon lungo o corto.
- Il sistema risolve il link corto.
- Estrae l'ASIN.
- Crea il link con il tag affiliato.
- Legge titolo e prezzo del prodotto.
- Per il prezzo usa prima l'XPath principale richiesto e poi i fallback XPath.
- Mostra i punti previsti prima del click.
- Quando l'utente clicca, viene mandato al link affiliato.

LOGICA PUNTI ATTUALE
- I punti NON sono sul prezzo intero.
- Formula: prezzo x commissione Amazon % x quota utente % x 100.
- Esempio elettronica 100 euro, commissione 3%, quota utente 20%:
  100 x 3% = 3 euro commissione
  3 x 20% = 0,60 euro utente
  0,60 x 100 = 60 punti

CATEGORIE PRECARICATE
- Elettronica 3%
- Videogiochi 1%
- Casa e cucina 7%
- Beauty 10%
- Salute 10%
- Sport 7%
- Abbigliamento 12%
- Scarpe 12%
- Gioielli 10%
- Libri 7%
- Giocattoli 7%
- Auto / Moto 5%
- Pet 8%
- Software 5%
- Alimentari 5%
- Ufficio 6%
- Bricolage 6%
- Prima infanzia 7%

PREMI PRECARICATI
- Gift card Amazon 5 euro = 500 punti
- Gift card Amazon 10 euro = 1000 punti
- Gift card Amazon 20 euro = 2000 punti
- Gift card Amazon 25 euro = 2500 punti
- Gift card Amazon 50 euro = 5000 punti
- Gift card Amazon 100 euro = 10000 punti

NOTE IMPORTANTI
- Se Amazon restituisce HTML diverso, captcha o pagina anti-bot, il prezzo può risultare non disponibile.
- L'XPath assoluto di Amazon può rompersi se cambia anche un solo div.
- Se aggiorni una piattaforma gia esistente, importa schema.sql o aggiungi manualmente le nuove tabelle e colonne.
