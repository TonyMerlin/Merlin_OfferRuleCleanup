# Merlin_OfferRuleCleanup

Bulk cleanup for expired cart price rules created by `Neklo_MakeAnOffer`.

## Use in Magento Admin

1. Open **Marketing â†’ Promotions â†’ Cart Price Rules** with an account allowed to manage cart price rules.
2. Check the count and cutoff date above the grid.
3. Click **Delete older Make an Offer rules** and confirm.
4. Keep the page open while the progress message updates. The button processes 50 rules per request and reports any rule IDs that could not be deleted.

If the browser or a request stops, refresh the page and run the cleanup again. Already deleted rules are skipped. Failed rule IDs remain eligible and can be retried after the underlying error is resolved; details are written to the Magento log.

## Which rules are selected

A cart price rule must meet **all** of these conditions:

- Name matches `Make an Offer #` followed by digits.
- Description is exactly `Auto Generation Coupon`.
- Coupon type is **Specific Coupon**.
- Discount action is **Fixed amount discount for whole cart** (`cart_fixed`).
- End date is on or before the date six calendar months before the current UTC date. For example, on 14 October 2026 the cutoff is 14 April 2026.

The cleanup includes rules still marked Active after their end date. It also includes older Make an Offer rules with different automatic generation or coupon usage settings. Rules without an end date and rules ended after the cutoff date are excluded. At month ends, the cutoff uses the last day of the target month if necessary (for example, 31 August becomes 28 February in a non-leap year).

Each rule is checked again immediately before deletion. Deletion uses Magento's sales rule repository, which also removes the rule's coupon records. Make an Offer request history is not deleted. There is no scheduled cleanup; an admin must start it.

## Command line

From the Magento root, run a read-only preview:

```bash
php bin/magento merlin:offer-rules:cleanup
```

The preview prints the candidate count and the first 20 rules. To delete all matching rules in batches of 100:

```bash
php bin/magento merlin:offer-rules:cleanup --execute
```

The command reports deletion failures and exits with a nonzero status if any rule fails. Rerunning it retries rules that remain eligible.

## Deployment

This module depends on `Magento_SalesRule` and `Neklo_MakeAnOffer`. On a new Magento installation, enable and register it:

```bash
php bin/magento module:enable Merlin_OfferRuleCleanup
php bin/magento setup:upgrade
```

In production mode, also compile dependency injection, deploy admin static content for the site's backend theme and locales, and clean the config, layout, and block HTML caches:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f --area adminhtml
php bin/magento cache:clean config layout block_html
```
