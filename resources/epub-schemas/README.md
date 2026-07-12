# Vendored EPUB 3 schemas

These RelaxNG schema files back the PHP-native structural validation in
`App\Services\EpubExporter` (feature: epub export v1, task 05). They let us catch a
generator regression that produces a non-conformant OPF **without** shipping the JVM-based
`epubcheck` tool. The export page separately links authors to the real
[epubcheck](https://www.w3.org/publishing/epubcheck/) for full conformance checking.

## What is here

| File | Purpose |
|------|---------|
| `package-30.rng` | EPUB 3 OPF (package document) grammar. `EpubExporter` validates the generated `OEBPS/book.opf` against this via `DOMDocument::relaxNGValidate()`. |
| `datatypes.rng` | Datatype module included by `package-30.rng`. |
| `epub-prefix-attr.rng` | `prefix` attribute module included by `package-30.rng`. |

## Provenance

Source: the [`w3c/epubcheck`](https://github.com/w3c/epubcheck) project's own schema
directory, `src/main/resources/com/adobe/epubcheck/schema/30/`, at commit
`82b174ec319ea3e6c9d2488f84155fa4a9171fc2` (fetched 2026-07-12). epubcheck is licensed
under the permissive BSD-2-Clause license.

The upstream files are RelaxNG **compact** syntax (`.rnc`), which libxml (PHP's
`DOMDocument`) cannot read. They were converted **once** to RelaxNG **XML** syntax (`.rng`)
with [trang](https://relaxng.org/jclark/trang.html) 20091111:

```
java -jar trang.jar -I rnc -O rng package-30.rnc package-30.rng
```

trang is only a build-time tool; nothing at runtime needs Java. To refresh these schemas,
re-fetch the three upstream `.rnc` files (`package-30.rnc` plus its `mod/datatypes.rnc` and
`mod/epub-prefix-attr.rnc` includes) and re-run the conversion.

## Why only the OPF, and not the nav / content documents

> [!NOTE]
> The task originally scoped schema validation of **both** the OPF and the EPUB 3 nav
> document. Only the OPF is schema-validated here; the nav and content documents are checked
> for **XML well-formedness** (`DOMDocument::loadXML()`) instead.

The EPUB 3 nav document's official grammar (`epub-nav-30.rnc`) pulls in the *entire*
XHTML 5 + MathML 3 + SVG RelaxNG grammar — over a hundred schema files across nested
subdirectories. libxml's RelaxNG engine cannot process that grammar (it rejects constructs
the full HTML5 schema relies on), so validating the nav against it would throw on
perfectly valid output. Because a schema failure is treated as a **server-side bug that
throws loudly**, a validator that false-fails is worse than none. The OPF grammar, by
contrast, is small, self-contained, and validates cleanly with libxml — so it is the one
document schema-validated here.
