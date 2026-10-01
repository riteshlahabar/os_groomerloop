# 2026-10-01 — Stripe payment gateway

**Scope:** The owner asked to wire up Stripe as the real payment gateway ("we use stripe for
payment method"). Billing (Phase 3b) was built against a `PaymentGateway` contract from the
start specifically so a real driver could be added without touching business logic (invariant
#5) — this session added that driver.
**Spec sections:** §24 Billing lifecycle, §30 Integrations, invariant #5 (provider abstraction)
**Outcome:** Completed. `BILLING_GATEWAY` still defaults to `fake` — switching to `stripe`
needs a real secret key, which this environment does not have.

## Changed

- `composer.json` / `composer.lock` — added `stripe/stripe-php` (`^17.0`, installed `v17.6.0`).
- `modules/Billing/Contracts/PaymentGateway.php` — added `setDefaultPaymentMethod(string
  $customerReference, string $token): void`. See `D-025` for why: `charge()` was already
  customer-scoped, not payment-method-scoped, and Stripe's off-session charge needs to know
  which stored card is the app's own default (`PaymentMethod.is_default`) or its own notion of
  "default" silently drifts from this app's.
- `modules/Billing/Services/Gateways/StripeGateway.php` — new driver. `createCustomer`,
  `storePaymentMethod` (attaches a client-tokenised Stripe PaymentMethod id — no card number
  ever reaches this application, §28), `forgetPaymentMethod` (detach, idempotent — swallows
  "already detached"), `setDefaultPaymentMethod` (`Customer::update` →
  `invoice_settings.default_payment_method`), `charge` (off-session `PaymentIntent` create +
  confirm against the customer's default payment method; a Stripe `CardException` becomes
  `ChargeResult::declined()`, never an exception — the existing dunning-cycle contract).
- `modules/Billing/Services/Gateways/FakePaymentGateway.php` — implements the new contract
  method (records the value; its own `charge()` has no use for it, but every driver must
  honestly implement the full contract).
- `modules/Billing/Actions/StorePaymentMethod.php` — after its transaction commits, calls
  `setDefaultPaymentMethod()` when the new card becomes the local default. Restructured to
  return `[$method, $isDefault]` from the transaction closure so the gateway call can happen
  after commit (the module's existing rule: a network round trip must never hold a DB lock
  open).
- `modules/Billing/Actions/ForgetPaymentMethod.php` — same pattern: after commit, if removing
  the default re-assigned a new local default, tells the gateway.
- `modules/Billing/BillingServiceProvider.php` — registered `'stripe' =>
  StripeGateway::class` in the driver map; a contextual binding supplies `services.stripe.secret`
  as `StripeGateway`'s constructor argument; the singleton resolver now throws a `RuntimeException`
  naming the problem if `BILLING_GATEWAY=stripe` is set with no secret key configured, rather
  than letting the Stripe SDK's own cryptic `api_key cannot be the empty string` surface instead.
- `config/services.php` — added `stripe.{key,secret,webhook_secret}`, reading
  `STRIPE_KEY`/`STRIPE_SECRET_KEY`/`STRIPE_WEBHOOK_SECRET`. Credentials live here (Laravel's
  conventional location, already used by Postmark/Resend/AWS/Slack), not in
  `modules/Billing/Config/billing.php`, which stays business policy.
- `.env` / `.env.example` — added `BILLING_GATEWAY=fake` (explicit, was previously implicit via
  the config default) and the three empty `STRIPE_*` keys, with a comment on what switches it
  over.
- `modules/Billing/Config/billing.php` — updated the `gateway` key's docblock: Stripe is wired,
  not "unblocked but not wired."
- `modules/Billing/Tests/Feature/StripeGatewayTest.php` — new. Required by
  `GatewayDriverGuardTest` (CI guard #6: every driver needs a contract-bound test, or the
  product's only real-money driver could go unverified while the suite stays green). Extends the
  shared `PaymentGatewayContract` trait and skips itself in `setUp()` when
  `services.stripe.secret` is empty — true here, since there is no real Stripe account to test
  against. Its own docblock flags a real gap in the shared contract trait for a provider whose
  declines are card-bound rather than description-bound (see "Not done" below).

## Verified

- `composer require stripe/stripe-php` → installed cleanly, no conflicts, "No security
  vulnerability advisories found."
- `composer dump-autoload` → regenerated, 7630 classes (up from 7257).
- `./vendor/bin/pint modules/Billing config/services.php --test` → passed.
- `ModuleBoundaryGuardTest` → passed (2/2) — `StripeGateway` only touches the `stripe/stripe-php`
  SDK and this module's own contracts/domain, no cross-module model reach.
- `GatewayDriverGuardTest` → passed (1/1) — both `FakePaymentGateway` and `StripeGateway` are
  now discovered and both have a contract-bound test.
- `php artisan tinker` — confirmed `app(PaymentGateway::class)` still resolves to
  `FakePaymentGateway` with the default config (no regression to existing behaviour).
- `php artisan tinker` — confirmed that forcing `billing.gateway` to `stripe` with no secret key
  configured throws the new, clear `RuntimeException` ("BILLING_GATEWAY is set to [stripe] but
  STRIPE_SECRET_KEY is not configured") rather than a cryptic SDK error. Caught one real bug
  this way: the first version of the guard checked `=== null`, but `env('STRIPE_SECRET_KEY')`
  with an empty-but-present `.env` line resolves to `''`, not `null` — fixed to check against
  the empty string and re-verified.
- **Not run:** the full `php artisan test` suite, and no real Stripe API calls were made
  (no account exists in this environment) — per the owner's standing instruction, verification
  was migration/Pint/guard-test/tinker only, as above.

## Not done

- **No real Stripe account or API keys exist in this environment.** `BILLING_GATEWAY` stays
  `fake` until the owner adds a real `STRIPE_SECRET_KEY` (test-mode key first, recommended) to
  `.env`. `StripeGatewayTest` will start actually running the moment that key is present — see
  the gap below before trusting its result blindly.
- **`StripeGatewayTest` has a documented, unresolved gap** (full detail in the test file's own
  docblock and in `D-025`'s consequences): the shared `PaymentGatewayContract` trait reuses one
  `acceptableToken()` for both "store this card" and "charge it successfully," and
  `decliningDescription()` for "charge it and expect a decline" — all against the *same* stored
  card. That convention matches the fake (which declines by reading the description string) but
  not Stripe (which declines based on which real test card/PaymentMethod was charged). Whoever
  adds real credentials needs to either give Stripe's test case a second, decline-specific test
  card, or widen the shared trait — not resolved here, since verifying either fix needs a real
  Stripe test-mode account this session didn't have.
- **No webhook endpoint.** `services.stripe.webhook_secret` is wired for configuration but
  nothing reads it yet — Stripe's own async events (a later decline on a previously-successful
  subscription, a disputed charge) are not consumed. Out of scope for "wire up the gateway
  itself"; flagged for whenever webhook handling is actually asked for.
- Client-side card collection (Stripe Elements/Stripe.js producing the PaymentMethod token that
  `storePaymentMethod()` expects) is frontend work, and the React SPA is still waiting on design
  files (`D-006`) — this session only built the server-side half the contract already assumed.

## Follow-ups

- [ ] Add a real Stripe test-mode secret key to `.env` when the owner has one, set
      `BILLING_GATEWAY=stripe`, and re-run `StripeGatewayTest` for the first time for real.
- [ ] Resolve the `acceptableToken()`/decline-card mismatch in `PaymentGatewayContract` before
      trusting `StripeGatewayTest`'s result once it stops skipping.
- [ ] Build the Stripe webhook endpoint once needed (subscription-level async events) —
      `services.stripe.webhook_secret` is already configured for it.
