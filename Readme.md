# Pay Plug Oney

Add guaranteed multipayment by Oney (3x / 4x with fees) to Thelia, on top of the PayPlug
payment module.

**Requires [PayPlugModule](https://github.com/thelia-modules/PayPlugModule)** — this module is a
satellite of it: it reuses its payment service, its payment events and its order-edit
back-office template.

Compatible with **Thelia 2 and Thelia 3** from a single codebase (see
[Thelia 3 support](#thelia-3-support)).

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is PayPlugOney.
* Activate it in your thelia administration panel

### Composer

```
composer require thelia/pay-plug-oney-module ~2.2.0
```

## Configuration

Oney has no configuration screen of its own: its settings are appended to **PayPlug's**
configuration page (`/admin/module/PayPlugModule`, anchor `#oneyConfiguration`). The module's
"Configuration" button in the module list redirects there.

| Setting | Effect |
|---|---|
| Enable multi payment by Oney | Makes Oney available as a payment method |
| Terms and conditions content | Content page the Oney terms get appended to |
| Terms hook confirmed | Declares that you display the Oney terms through your theme instead |

Oney legally requires its terms to be reachable, so saving fails unless one of the two options
is set.

**Amount range** — Oney only accepts orders between **100 € and 3 000 €**. The bounds live in
`Service\OneyEligibilityChecker`.

## Thelia 3 support

The module runs unchanged on both versions. The Thelia 3 code paths are gated on
`PayPlugOney::isThelia3()` (which tests for `Thelia\Core\Install\Database`), and the Thelia 2
Smarty templates are kept in place for existing installs.

> **Note** — PayPlugModule itself is currently Thelia 3 only (it imports
> `Thelia\Core\Install\Database` and `Thelia\Domain\Taxation\TaxEngine\TaxEngine`). Since it is
> a required dependency, PayPlugOney's Thelia 2 paths cannot actually execute until
> PayPlugModule becomes bi-compatible too. They are kept ready for that.

### What changed

| Concern | Thelia 2 | Thelia 3 |
|---|---|---|
| Back-office configuration | `Hook\ConfigurationHook` → Smarty `hook_configuration.html` | `Hook\Back\OneyConfigurationHook` → `backOffice/default-twig/PayPlugOney/oney-configuration.html.twig` |
| Order edit block | `Hook\BackHook` (asset resolver indirection) | `Hook\Back\OrderEditHook` → reuses PayPlugModule's `order_pay_plug.html.twig` |
| Instalment selector at checkout | `order-invoice.payment-extra` Smarty hook | Payment module options API (`TheliaEvents::MODULE_PAYMENT_GET_OPTIONS`) |
| Simulation pop-in | Hand-written `XMLHttpRequest` + `innerHTML` | `PayPlugOney:OneySimulation` LiveComponent |
| Oney terms and conditions | `pay_plug_oney.terms_and_conditions` front hook | `/oney/terms-and-conditions` route |
| Tax engine access | `Thelia\TaxEngine\TaxEngine` injected by type | `Service\OneyCartAmountResolver`, injected by service id |

### Payment module options

On Thelia 3, `Thelia\Domain\Module\Payment\PaymentModuleService` dispatches
`TheliaEvents::MODULE_PAYMENT_GET_OPTIONS` to build `GET /api/front/payment/modules`. The Oney
plans are published there by `EventListener\PaymentOptionListener` as an option group named
`pay_plug_oney_type`:

```json
{
  "optionGroups": [
    {
      "code": "pay_plug_oney_type",
      "title": "Choose the number of payments",
      "minimumSelectedOptions": 1,
      "maximumSelectedOptions": 1,
      "options": [
        { "code": "x3_with_fees", "title": "Pay in 3 times", "description": "<span…>" }
      ]
    }
  ]
}
```

The legacy OpenApi mechanism dispatches the **same event name** with a **different event
class**, so the listener accepts `ActionEvent` and branches on `instanceof`. Type-hinting either
concrete class raises a fatal `TypeError` when the other mechanism fires — worth knowing if you
write a similar listener.

> ⚠️ **Theme work required.** Publishing the options is not enough to display them. A theme's
> payment step must render `optionGroups[].options[]` (as radio inputs, one group at a time).
> The Flexy `PaymentModules` component only renders `title`, `id` and `selected` out of the box,
> so the 3x/4x selector is exposed by the API but invisible at checkout until the theme is
> extended.
>
> `option.description` is HTML and must be output with `|raw` (Twig autoescapes by default).
> It is escaped at the source by `Service\OneySimulationHtmlRenderer` — that renderer is the
> only escaping barrier, since neither API Platform nor `json_encode` neutralises tags.

### Front-office integration

Do **not** reference the LiveComponent or a module Twig function directly from a theme
template: those references are resolved at compile time, so the whole page breaks if the module
is disabled. Use the routes instead.

| Route | Purpose |
|---|---|
| `GET /oney/check_simulation?amount=<cents>` | `{"isValid": bool}` — eligibility, no PayPlug API call |
| `GET /oney/simulate?amount=<cents>` | Renders the simulation pop-in |
| `GET /oney/terms-and-conditions` | Renders the Oney terms |
| `POST /oney/payment_form` | `{"errors": []}` — pre-validates Oney's constraints |

```js
// Fetch the simulation only when the customer asks for it: the PayPlug simulation endpoint
// is a paid third-party call.
const response = await fetch(`/oney/simulate?amount=${amountInCents}`)
document.querySelector('#oney-simulation').innerHTML = await response.text()
```

The component expects the theme to style the `.oneyCta` / `.oneyPopin` / `.oneyOption` classes.
On Thelia 2 they come from the `main.stylesheet` hook; on Thelia 3 there is no such hook, so
copy the rules from `templates/frontOffice/default/PayPlugOney/hook/oneyStyleSheet.html` into
your theme.

## Architecture

```
Service/
  OneyEligibilityChecker      Amount range — single source of truth, static (no state, no deps)
  OneyCartAmountResolver      Only point of contact with the tax engine; amounts in cents
  OneySimulationPresenter     PayPlug payload → OneySimulationOption DTOs; Oney business rules
  OneySimulationHtmlRenderer  DTO → escaped HTML instalment breakdown
  OrderPayPlugDataPresenter   Variables for PayPlugModule's order-edit template
  OneyService                 extends PayPlugModule\Service\PaymentService
  OrderStatusService          Owns the `oney_authorization_pending` status
Contract/
  TaxContextProviderInterface Structural PHPDoc type — implemented by nobody, see the class
```

Amounts are **always in cents**, matching the PayPlug API. Method names state their unit; use
`OneyEligibilityChecker::toCents()` to convert, never a bare `(int) ($euros * 100)` cast, which
truncates (`(int) (100 * 23.10) === 2309`).

### Simulation caching

`OneyService::getSimulation()` is a synchronous HTTP call to a **paid** third-party endpoint,
sitting on the checkout's critical path. `TheliaEvents::MODULE_PAYMENT_GET_OPTIONS` fires on
every build of the payment method list — every reload of the payment step, every address
change, every coupon — so `OneySimulationPresenter` caches on two levels:

- per-request memoisation, keyed by amount;
- a shared `cache.app` entry, keyed by amount, **TTL 300 s**
  (`OneySimulationPresenter::CACHE_TTL_IN_SECONDS`).

Oney's schedule for a given amount is a fixed scale with no customer scoring, so it is safe to
reuse for a few minutes. Failures are deliberately **not** cached: the exception propagates out
of the cache callback so a transient PayPlug outage does not deprive every visitor of Oney for
the whole TTL.

`/oney/simulate` is public, and the per-amount cache alone does not stop abuse: an attacker can
walk the eligible range (~290 000 distinct cent values) and miss the cache every time.
`OneySimulationThrottle` therefore caps simulations at **30 per minute per client IP**, returning
`429` beyond that.

The counter is keyed on the **IP**, not the session: a caller sending no cookie gets a fresh
session — and a fresh counter — on every request, which is exactly what an abusive script does.
It is also fail-open (a cache outage must never close the checkout) and best-effort under
concurrency. `symfony/rate-limiter` was deliberately not used, as it is not installed and adding
it would change the project's composer dependencies.

### Template layout

```
templates/
├── backOffice/default/PayPlugOney/…        Smarty, Thelia 2
├── backOffice/default-twig/PayPlugOney/…   Twig, Thelia 3
├── frontOffice/default/PayPlugOney/…       Smarty, Thelia 2
├── frontOffice/flexy/PayPlugOney/…         Twig, Thelia 3
└── components/OneySimulation.html.twig     LiveComponent
```

A module's template directory is named after the **base theme** it targets, never after a
project theme: `flexy` for the Thelia 3 front-office (from which `vallereuil-scierie` and any
other project theme inherit), `default-twig` for the Thelia 3 back-office, `default` for the
Thelia 2 Smarty templates. The two engines therefore never share a directory.

LiveComponent templates are the exception and sit in `templates/components/`, outside any theme
directory — they belong to no theme, being rendered through `#[AsLiveComponent(template: …)]`
rather than by a theme's template resolver. This is the established Thelia 3 convention
(`StockAlert`, `OrderComment`, `CreditAccount`, `HeaderHighlights`, `TheliaLibrary`,
`DuplicateOrder` all do this).

Templates rendered outside the theme resolver are addressed through the module's Twig namespace,
which points at `templates/` — hence the full path, e.g.
`@PayPlugOneyModule/frontOffice/flexy/PayPlugOney/simulation.html.twig`.

### Conventions worth knowing

- **Routes are declared in `Config/routing.xml` only.** Never add `#[Route]`: Thelia 2 does not
  read PHP attributes (routes vanish silently) and Thelia 3 loads both mechanisms (routes get
  registered twice).
- **Thelia 2 and Thelia 3 hooks use different method names.** `module_hook` rows are keyed by
  (module, hook, method), so a shared name makes one registration overwrite the other.
- **Declare Thelia 2 hooks with `method=`, never `templates=`.** A `templates=` declaration is
  rendered without calling the PHP method, bypassing the `isThelia3()` guard.
- **Back-office hook templates are prefixed with the module name.** The Twig namespace is flat
  across modules, so an unprefixed name silently collides.

## Known issues

- **Refund notifications are not handled.** The listener used to subscribe
  `RefundNotificationEvent` to a `handleRefundNotification` method that does not exist, so every
  refund notification failed. The broken subscription was removed rather than given an invented
  behaviour: deciding which status a partial refund should produce is a business decision.
  See `EventListener\NotificationListener`.
- The Smarty templates under `templates/frontOffice/default` and `templates/backOffice/default`
  are retained for Thelia 2 installs; a Thelia 3 back-office does not render them.
- **`Hook\FrontHook::addBaseSimulationJs()`** and its template
  `templates/frontOffice/default/PayPlugOney/hook/baseSimulationJs.html` are dead code: the hook
  has never been declared in `config.xml`, so the method is never invoked. Left in place pending
  a decision to remove it.
- **`PayPlugModule` still has the N+1** on order-edit taxes that `OrderPayPlugDataPresenter`
  fixes on this side. It cannot be fixed durably there: the module is installed by composer into
  gitignored `vendor/`, so any edit is wiped by the next `composer install`. It needs an upstream
  patch on `thelia/pay-plug-module`.

## Changelog

### 2.2.0

- Thelia 3 support (bi-compatible with Thelia 2)
- Back-office rendered in the `default-twig` theme
- Instalment selector published through the native payment module options API
- Simulation pop-in rewritten as a LiveComponent, replacing ~120 lines of manual JavaScript
- `declare(strict_types=1)` across the module
- **Security**: fixed an IDOR in `POST /oney/payment_form` — the invoice address id came
  straight from the request and was looked up without checking ownership, letting anyone probe
  other customers' addresses through the error messages
- Fixed the terms-and-conditions content selector, which submitted the content *title* instead
  of its id
- Fixed the `order.edit-js` hook name (it carried a trailing space, so it never registered);
  the hook was then dropped as PayPlugModule already injects that script
- Payment failures and hook errors are now logged instead of silently swallowed
- Removed dead imports, and the duplicated instalment markup in `simulation.html` now includes
  `simulationBlock.html`
- **Performance**: simulations are memoised per request and cached for 5 minutes, instead of one
  PayPlug HTTP call per payment-method-list build; closing the simulation pop-in no longer costs
  a server round trip
- **Security**: form-supplied `success_url`/`error_url` are confined to the current host before
  redirecting, and the submitted instalment plan is whitelisted before being stored in session
- **Security**: `/oney/simulate` is rate limited to 30 simulations per minute per IP, so the
  public route can no longer be used to run up PayPlug API calls
- **Performance**: order-edit taxes are loaded in one query instead of one per order line, and
  the configuration form eager-loads content translations
