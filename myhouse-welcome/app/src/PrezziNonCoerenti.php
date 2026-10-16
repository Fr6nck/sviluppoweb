<?php
namespace MHW;

/** 9A · I prezzi su Stripe non tornano con il listino (Stripe::verificaPrezzi, controllaSessione): niente Checkout. */
final class PrezziNonCoerenti extends \RuntimeException {}
