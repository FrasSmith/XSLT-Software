# PPTX Checker PHP

A standalone PHP 8.1+ application that generates accessibility reports from `.pptx` presentations. It preserves the existing project's XSLT-generated HTML while also exposing structured JSON for third-party integrations.

## Requirements

- PHP 8.1 or later
- Composer
- PHP extensions: DOM, JSON, libxml, XSL and ZIP
- A writable system temporary directory

The web server document root must be `public/`; do not expose `src/`, `resources/`, `templates/` or `vendor/` directly.

## Architecture and data flow

The application is intentionally framework-free so it can run on conventional PHP hosting. `public/index.php` is the production front controller and both user interfaces call the same processing service:

```text
Browser form ─┐
              ├─> PresentationProcessor ─> AnalysisResult
JSON API ─────┘             │
                            ├─> PresentationInspector ─> AccessibilityReviewer
                            └─> bundled XSLT ──────────> legacy HTML report
```

The principal responsibilities are:

- `PresentationProcessor` owns input validation, ZIP/XML limits, temporary-file isolation, orchestration, XSLT security, and cleanup.
- `PresentationInspector` reads semantic information directly from OOXML before the XSLT discards metadata needed for accessibility checks.
- `AccessibilityReviewer` reports non-decorative images whose description/alt-text field is empty.
- `ReportParser` adapts the legacy XSLT HTML into the original structured API fields; it is not the source of accessibility findings.
- `AnalysisResult` combines the original report fields with the additive `review` object used by the HTML and JSON outputs.

A presentation is copied to a random owner-only temporary directory, opened as an OOXML ZIP archive, and represented as one combined XML document containing slides, relationships, layouts, and masters. That document is inspected for accessibility metadata and separately passed through the unchanged XSLT. Cleanup runs from a `finally` block whether processing succeeds or fails.

## Install

```bash
composer install --no-dev --classmap-authoritative
```

For development and tests:

```bash
composer install
composer test
```

## Local development

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

Open `http://127.0.0.1:8080/` to use the browser upload form.

## Endpoints

### Browser report

`GET /` displays the upload form. The form submits a `.pptx` file to `POST /report`, which returns an accessibility review followed by the existing XSLT-generated content report.

The upload form can optionally generate an A4 portrait PDF alongside the HTML report. When selected, the browser starts the PDF download automatically after the HTML results load; no PDF control is displayed on the result page. PDFs are stored as private temporary files, expire after 15 minutes, and are deleted immediately after the one-time download. PDF generation is not available through the JSON API.

### JSON API

Send a multipart upload with the field name `file`:

```bash
curl -X POST -F 'file=@presentation.pptx' http://127.0.0.1:8080/api/v1/analyse
```

A successful response contains both the compatible HTML report and structured slide data:

```json
{
  "filename": "presentation.pptx",
  "slideCount": 1,
  "reportHtml": "<p class=\"lead\">...</p>",
  "slides": [
    {
      "number": 1,
      "text": "Slide 1 ...",
      "images": [
        {
          "filename": "../media/image1.png",
          "description": ""
        }
      ]
    }
  ],
  "review": {
    "summary": {
      "images": 1,
      "errors": 1,
      "warnings": 0,
      "passed": 0,
      "excluded": 0
    },
    "slides": [
      {
        "number": 1,
        "title": "Quarterly revenue",
        "findings": [
          {
            "imageNumber": 1,
            "filename": "../media/image1.png",
            "description": "",
            "code": "image.alt.missing",
            "status": "missing",
            "severity": "error",
            "score": null
          }
        ]
      }
    ]
  }
}
```

Processing and validation failures use HTTP 422 with an `error` property. Unknown API endpoints return 404, and unexpected failures return 500 without exposing internals.

The API is currently unauthenticated. Production deployments should add authentication and rate limiting at the web-server, reverse-proxy, or application layer.

## Accessibility review

The simplified deterministic review does not use an AI model or external service. It reports only non-decorative images whose PowerPoint description/alt-text field is empty. Images with any description are not scored or shown in an in-slide review box. Decorative images are counted as images but do not require alt text.

## Configuration

Limits can be configured with environment variables:

| Variable | Default |
|---|---:|
| `PPTX_MAX_UPLOAD_BYTES` | 52,428,800 (50 MiB) |
| `PPTX_MAX_ARCHIVE_ENTRIES` | 5,000 |
| `PPTX_MAX_EXPANDED_BYTES` | 524,288,000 (500 MiB) |
| `PPTX_MAX_XML_ENTRY_BYTES` | 52,428,800 (50 MiB) |

PHP's `upload_max_filesize`, `post_max_size`, `memory_limit`, and request timeout must also be configured appropriately. PHP or web-server limits can reject a request before the application receives it.

## Processing and privacy

Each presentation is copied into a randomly named private directory under the system temporary directory. Cleanup runs in a `finally` block after success or failure. The original client filename is used only in the response, not as a storage path.

Generated PDF reports are written to randomly named owner-only files in the system temporary directory. Access requires an unguessable token tied to the browser session. Each link expires after 15 minutes and is consumed after one successful download.

The processor verifies basic OOXML structure and limits archive entry count, total expanded size, individual XML size, and uploaded size. XML network access and XSLT file/network operations are disabled.

## XSLT compatibility

`resources/pptx.xsl` retains the original transformation structure with fixes for text spacing, list boundaries, relationship lookup, and semantic bold/italic run formatting.

## Apache

Point the virtual host's `DocumentRoot` at `public/` when server configuration is available. The included `public/.htaccess` routes non-file requests to `index.php`; Apache must permit overrides and enable `mod_rewrite`.

For Nginx or another server, configure a front-controller fallback to `public/index.php`.

## SFTP deployment

An example SFTP deployment guide is available at [`docs/deployment/mythic-beasts-sftp.md`](docs/deployment/mythic-beasts-sftp.md). It uses generic `USERNAME` and `DOMAIN` placeholders and does not contain a live deployment layout.
