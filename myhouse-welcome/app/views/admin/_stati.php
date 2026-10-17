<?php
/* Gli stati che arrivano dal database e da Stripe, come si leggono in amministrazione.
   Un valore che qui manca si mostra così com'è: meglio un codice che una riga vuota. */
$statoOrdine = ['pending' => 'In attesa', 'awaiting' => 'In verifica', 'paid' => 'Pagato', 'failed' => 'Non riuscito', 'canceled' => 'Annullato', 'expired' => 'Scaduto'];
$statoAbbonamento = ['active' => 'Attivo', 'trialing' => 'In prova', 'past_due' => 'Rinnovo non riuscito', 'canceled' => 'Terminato', 'incomplete' => 'Incompleto', 'unpaid' => 'Non pagato'];
$statoPagamento = ['paid' => 'pagato', 'failed' => 'pagamento non riuscito', 'manuale' => 'manuale', 'dimostrazione' => 'di esempio'];
$viaPagamento = ['stripe' => 'Stripe', 'manuale' => 'manuale', 'dimostrazione' => 'di esempio'];
