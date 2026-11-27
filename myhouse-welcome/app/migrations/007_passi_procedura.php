<?php
/**
 * 007 — La procedura passa da sette a cinque passi.
 *
 * properties.wizard_step ricorda fin dove è arrivato l'host: i nomi vecchi
 * diventano quelli del passo nuovo che li contiene. Nessun dato si perde:
 * cambia solo l'etichetta del punto in cui si riprende.
 *   checkin → arrivo · contenuti → sezioni · lingue → aspetto · anteprima → pubblica
 *   struttura, sezioni, aspetto, fatto: invariati
 * Un solo UPDATE con CASE: vale uguale su SQLite e MySQL.
 */
return function (\PDO $pdo): void {
    $pdo->exec("UPDATE properties SET wizard_step = CASE wizard_step
                    WHEN 'checkin' THEN 'arrivo'
                    WHEN 'contenuti' THEN 'sezioni'
                    WHEN 'lingue' THEN 'aspetto'
                    WHEN 'anteprima' THEN 'pubblica'
                    ELSE wizard_step END
                WHERE wizard_step IN ('checkin', 'contenuti', 'lingue', 'anteprima')");
};
