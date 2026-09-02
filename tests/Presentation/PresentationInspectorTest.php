<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Tests\Presentation;

use DOMDocument;
use Effortmark\PptxChecker\Presentation\PresentationInspector;
use PHPUnit\Framework\TestCase;

final class PresentationInspectorTest extends TestCase
{
    public function testItReadsPowerPointDecorativeImageMetadata(): void
    {
        $document = new DOMDocument();
        $document->loadXML(<<<'XML'
            <files>
              <file name="ppt/slides/slide1.xml" num="1">
                <p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
                       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
                       xmlns:adec="http://schemas.microsoft.com/office/drawing/2017/decorative">
                  <p:cSld><p:spTree><p:pic>
                    <p:nvPicPr><p:cNvPr id="2" name="Picture" descr=""><a:extLst><a:ext><adec:decorative val="1"/></a:ext></a:extLst></p:cNvPr></p:nvPicPr>
                    <p:blipFill><a:blip r:embed="rId1"/></p:blipFill>
                  </p:pic></p:spTree></p:cSld>
                </p:sld>
              </file>
              <file name="ppt/slides/_rels/slide1.xml.rels" num="1">
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                  <Relationship Id="rId1" Target="../media/image1.png"/>
                </Relationships>
              </file>
            </files>
            XML,
            LIBXML_NONET,
        );

        $slides = (new PresentationInspector())->inspect($document);

        self::assertTrue($slides[0]['images'][0]['decorative']);
        self::assertSame('../media/image1.png', $slides[0]['images'][0]['filename']);
    }
}
