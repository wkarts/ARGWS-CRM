# Shared native sales form source

`assets/js/native-admin-sales-form.js` contains the original native invoice/sales
form functions and event handlers, extracted rather than reimplemented. The
principal runtime must load it as well as the scoped runtime, before `main.js`.
It requires the existing native vendor bundle (jQuery, accounting, validation,
selectpicker, sortable), shared UI/date/search helpers, `app`, and the native
`billingAndShippingFields` declaration. It does not authorize any route.

The extraction retains transaction submit, recurrence controls, customer/project
selection, item preview/order/taxes, totals, currency, merge/expense selection,
and the existing form validation. Functions needed by other native sales forms
remain available under the same names. `hidden_input` and `slugify` remain in `app.js`, compiled into the common bundle
used by the portal and both admin contexts; they are not admin-only handlers.

The source manifest locks each extracted function and the ready handlers against
unintentional changes. Node tests check unique ownership and exercise the actual
loaded functions; they do not establish browser behavior or HTTP authorization.
Registration in both presentations and complete invoice endpoint adaptation are
integration prerequisites. No missing endpoint is replaced with an empty result,
and no scoped admission should be widened merely because its UI is now loaded.

## Number validation adaptation

The shared `validate_invoice_form` now adds the persisted parent ID/revision only
when the native form supplies those hidden fields. The legacy wire is unchanged.
`Invoices::validate_invoice_number` uses the live request context and real canonical
allocator (new/draft), or validates the issued document plus immutable stored
number/date and revision. The browser's `original_number` is never authority.
Remote validation does not reserve a number and does not replace final writer
revalidation. The updated source manifest records this intentional adaptation.
