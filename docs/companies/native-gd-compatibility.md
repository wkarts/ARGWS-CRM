# PHP8.5 GD compatibility

The strict focused runner exposed TCPDF calling `imagedestroy()` in its transparent-PNG PDF path. PHP documents that the function has no effect since8.0 and is deprecated since8.5: https://www.php.net/manual/en/function.imagedestroy.php .

This narrow local compatibility patch retains resource cleanup only on PHP versions below8.0. It changes six TCPDF call sites and one native bounded-image call site, not the library version, image processing, output, error reporting or test assertions. No warning suppression is introduced.

The focused regression runs real transparent-PNG PDF generation, both barcode renderers, PNG/JPEG conversion and bounded image normalization with all diagnostics fatal. Existing invoice/PDF tests remain. Local PHP8.4 passed; PHP8.1/8.5 proof must come from the focused CI matrix.
