<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\App;
use ArcoDelVento\Http\Request;
use ArcoDelVento\Http\Response;
use ArcoDelVento\I18n\Routes;
use ArcoDelVento\Mail\MailMessage;
use ArcoDelVento\Support\Csrf;
use ArcoDelVento\Support\Validator;

/**
 * Il modulo dei contatti.
 *
 * Convalida lato server — quella del browser è un aiuto, non una difesa — e
 * Post/Redirect/Get dopo l'invio riuscito, così ricaricare la pagina di
 * ringraziamento non rispedisce il messaggio.
 */
final class ContactController
{
    private const SESSION_SENT = '_adv_contact_sent';

    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request): Response
    {
        if ($request->isPost()) {
            return $this->submit($request);
        }

        $sent = (bool) ($_SESSION[self::SESSION_SENT] ?? false);
        unset($_SESSION[self::SESSION_SENT]);

        return $this->render(['inviato' => $sent]);
    }

    private function submit(Request $request): Response
    {
        if (!Csrf::isValid($request->post['_token'] ?? null)) {
            return $this->render([
                'errori' => ['_form' => 'token'],
                'valori' => $request->post,
            ], 400);
        }

        // Trappola per i robot: un campo che un essere umano non vede e non
        // compila. Se è pieno, si risponde «grazie» e non si spedisce nulla —
        // meglio che un CAPTCHA, che costa fatica a chi scrive davvero.
        if (trim((string) ($request->post['indirizzo_2'] ?? '')) !== '') {
            $_SESSION[self::SESSION_SENT] = true;

            return Response::redirect(Routes::url('contact', $this->app->locale()), 303);
        }

        $v = new Validator($request->post);
        $v->required('nome')->maxLength('nome', 80)
          ->required('email')->email('email')->maxLength('email', 180)
          ->maxLength('telefono', 40)
          ->required('messaggio')->minLength('messaggio', 10)->maxLength('messaggio', 4000)
          ->accepted('privacy');

        if ($v->fails()) {
            return $this->render(['errori' => $v->errors(), 'valori' => $request->post], 422);
        }

        $this->deliver($v);

        $_SESSION[self::SESSION_SENT] = true;

        return Response::redirect(Routes::url('contact', $this->app->locale()), 303);
    }

    private function deliver(Validator $v): void
    {
        $soggetti = $this->app->translator()->list('contact.form.subjects') ?? [];
        $chiave   = $v->value('oggetto', 'info');
        $oggetto  = (string) ($soggetti[$chiave] ?? $chiave);

        $body = implode("\n", [
            'Nuovo messaggio dal modulo contatti del sito.',
            '',
            'Oggetto:  ' . $oggetto,
            'Nome:     ' . $v->value('nome'),
            'E-mail:   ' . $v->value('email'),
            'Telefono: ' . ($v->value('telefono') !== '' ? $v->value('telefono') : '—'),
            'Lingua:   ' . $this->app->locale(),
            '',
            'Messaggio:',
            $v->value('messaggio'),
        ]);

        $this->app->mailer()->send(new MailMessage(
            to:          (string) $this->app->config('mail.to'),
            subject:     '[Arco del Vento] ' . $oggetto . ' — ' . $v->value('nome'),
            body:        $body,
            fromAddress: (string) $this->app->config('mail.from.address'),
            fromName:    (string) $this->app->config('mail.from.name'),
            // Rispondere al messaggio scrive all'ospite, non a sé stessi.
            replyTo:     $v->value('email'),
        ));

        // Anche nell'area riservata; se non riesce, il messaggio è comunque partito.
        try {
            $this->app->inbox()->add('messaggio', [
                'oggetto'   => $oggetto,
                'nome'      => $v->value('nome'),
                'email'     => $v->value('email'),
                'telefono'  => $v->value('telefono'),
                'messaggio' => $v->value('messaggio'),
                'lingua'    => $this->app->locale(),
            ]);
        } catch (\Throwable $e) {
            error_log('Messaggio non salvato nell\'area riservata: ' . $e->getMessage());
        }
    }

    /** @param array<string,mixed> $data */
    private function render(array $data, int $status = 200): Response
    {
        $locale = $this->app->locale();

        return Response::html($this->app->view()->page('contact', $data + [
            'pagina'      => 'contact',
            'inviato'     => false,
            'errori'      => [],
            'valori'      => [],
            'titoloSeo'   => $this->app->translator()->get('contact.seo_title'),
            'descrizione' => $this->app->translator()->get('contact.seo_description'),
            'canonico'    => Routes::url('contact', $locale),
            'alternative' => array_combine(
                $this->app->config('i18n.available'),
                array_map(
                    static fn (string $l): string => Routes::url('contact', $l),
                    $this->app->config('i18n.available')
                )
            ),
        ]), $status);
    }
}
