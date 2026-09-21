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

    public function testItAssignsTheSameIdentifierToPicturesInAGroup(): void
    {
        $document = new DOMDocument();
        $document->loadXML(<<<'XML'
            <files>
              <file name="ppt/slides/slide1.xml" num="1">
                <p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
                       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                  <p:cSld><p:spTree><p:grpSp>
                    <p:nvGrpSpPr><p:cNvPr id="7" name="Diagram" descr="Overall diagram description"/></p:nvGrpSpPr>
                    <p:pic><p:nvPicPr><p:cNvPr id="8" name="Part 1" descr=""/></p:nvPicPr><p:blipFill><a:blip r:embed="rId1"/></p:blipFill></p:pic>
                    <p:grpSp>
                      <p:nvGrpSpPr><p:cNvPr id="10" name="Nested group"/></p:nvGrpSpPr>
                      <p:pic><p:nvPicPr><p:cNvPr id="9" name="Part 2" descr="Diagram description"/></p:nvPicPr><p:blipFill><a:blip r:embed="rId2"/></p:blipFill></p:pic>
                    </p:grpSp>
                  </p:grpSp></p:spTree></p:cSld>
                </p:sld>
              </file>
              <file name="ppt/slides/_rels/slide1.xml.rels" num="1">
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                  <Relationship Id="rId1" Target="../media/part1.png"/>
                  <Relationship Id="rId2" Target="../media/part2.png"/>
                </Relationships>
              </file>
            </files>
            XML,
            LIBXML_NONET,
        );

        $images = (new PresentationInspector())->inspect($document)[0]['images'];

        self::assertSame('slide1-group-7', $images[0]['groupId']);
        self::assertSame($images[0]['groupId'], $images[1]['groupId']);
        self::assertSame('Diagram description', $images[1]['description']);
        self::assertSame('Overall diagram description', $images[0]['groupDescription']);
        self::assertSame($images[0]['groupDescription'], $images[1]['groupDescription']);
    }
}
