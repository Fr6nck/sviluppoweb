<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\App;
use ArcoDelVento\Http\Request;
use ArcoDelVento\Http\Response;

/**
 * POST /pagamenti/sumup — SumUp avvisa che un pagamento è cambiato.
 *
 *     {"event_type": "CHECKOUT_STATUS_CHANGED", "id": "…"}
 *
 * La notifica non è firmata, quindi non si crede a niente di quello che
 * contiene: l'identificativo serve solo a sapere quale pagamento chiedere a
 * SumUp, con la chiave. Un identificativo che non appartiene a nessuna
 * prenotazione non fa niente. Si risponde sempre 204, subito: SumUp
 * riprova le notifiche a cui non si risponde con un 2xx.
 */
final class PaymentController
{
    public function __construct(private readonly App $app)
    {
    }

    public function notifica(Request $request): Response
    {
        $vuota = Response::vuota(204)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');

        $pagamenti = $this->app->pagamenti();
        if (!$request->isPost() || $pagamenti === null) {
            return $request->isPost() ? $vuota : Response::vuota(405)->withHeader('Allow', 'POST');
        }

        $corpo = (string) file_get_contents('php://input', false, null, 0, 8192);
        $dati  = json_decode($corpo, true);
        $id    = is_array($dati) ? (string) ($dati['id'] ?? '') : '';
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $id) === 1) {
            try {
                $pagamenti->daNotifica($id);
            } catch (\Throwable $e) {
                error_log('Notifica SumUp ' . $id . ': ' . $e->getMessage());
            }
        }

        return $vuota;
    }
}
