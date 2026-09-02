<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
    xmlns:z="http://schemas.openxmlformats.org/package/2006/relationships"
    xmlns:xs="http://www.w3.org/2001/XMLSchema" exclude-result-prefixes="a r p z xs">

    <xsl:output method="html" indent="yes" version="5.0"/>
    <xsl:param name="debug">0</xsl:param>

    <!-- list of slides -->
    <xsl:variable name="slides" select="//file[contains(@name, 'slides/slide')]"/>

    <!-- list of slide rels -->
    <xsl:variable name="slides.rels" select="//file[contains(@name, 'slides/_rels/slide')]"/>

    <!-- list of slide layouts -->
    <xsl:variable name="slides.layout" select="//file[contains(@name, 'slideLayouts/slideLayout')]"/>

    <!-- list of slide layout rels -->
    <xsl:variable name="slides.layout.rels"
        select="//file[contains(@name, 'slideLayouts/_rels/slideLayout')]"/>

    <!-- list of slide master layouts -->
    <xsl:variable name="slides.master" select="//file[contains(@name, 'slideMasters/slideMaster')]"/>

    <!-- here is the start of the formatter -->
    <xsl:template match="/">

        <p class="lead">Total slides: <xsl:value-of select="count($slides)"/></p>

        <xsl:for-each select="$slides">
            <!-- sorting via num attribute (added via python script) -->
            <xsl:sort select="@num" data-type="number"/>
            <!-- container div for each slide -->
            <div class="slide-item">
                <h1 class="h4">Slide <xsl:value-of select="position()"/></h1>
                <!-- initial template for a slide -->
                <xsl:apply-templates select=".//p:sp | .//p:pic | .//p:graphicFrame"/>
            </div>
        </xsl:for-each>
    </xsl:template>

    <!-- general template for text container -->
    <xsl:template match="p:sp[.//a:r]">
        <xsl:variable name="slide.name" select="ancestor::file/@name"/>
        <xsl:variable name="slide.num"
            select="substring-before(substring-after($slide.name, '/slides/slide'), '.xml')"/>
        <xsl:variable name="slide.rels.name" select="concat('_rels/slide', $slide.num, '.xml.rels')"/>
        <xsl:variable name="slide.layout.name"
            select="substring-after($slides.rels[contains(@name, $slide.rels.name)]/*/*/@Target[contains(., 'slideLayouts')], '../')"/>
        <xsl:variable name="slide.layout.node"
            select="$slides.layout[contains(@name, $slide.layout.name)]"/>

        <xsl:variable name="shape.index" select=".//p:ph/@idx"/>
        <xsl:variable name="shape.type" select=".//p:ph/@type"/>

        <!-- check if there are any shape type -->
        <xsl:variable name="isShapeType">
            <xsl:choose>
                <xsl:when test="$shape.type = 'title' or $shape.type = 'ctrTitle'">
                    <xsl:value-of select="$shape.type"/>
                </xsl:when>

                <!-- lists -->
                <xsl:when
                    test="$slide.layout.node//p:sp[.//p:nvPr//p:ph/@idx = $shape.index]//a:p/a:pPr[@lvl]">
                    <xsl:choose>
                        <!-- body with list, but bu:none in entry -->

                        <xsl:when test="$shape.type = 'body' and .//a:buNone">body</xsl:when>
                        <xsl:when
                            test="$slide.layout.node//p:sp[.//p:nvPr//p:ph/@idx = $shape.index]//a:lstStyle/a:lvl1pPr/a:buNone"
                            >body, lvl1 property in slide layout -> bu:none</xsl:when>
                        <xsl:when
                            test="$shape.type = 'body' and (.//a:p/a:pPr[@marL = '0' and @indent = '0'])"
                            >body, indent and marL = 0</xsl:when>
                        <xsl:otherwise>list, in slide layout via index,
                            lvl-attribute</xsl:otherwise>
                    </xsl:choose>
                </xsl:when>
                <xsl:otherwise>body, no type</xsl:otherwise>
            </xsl:choose>
        </xsl:variable>

        <xsl:variable name="isShapeIndex">
            <xsl:choose>
                <xsl:when test="$shape.index != ''">
                    <xsl:value-of select="$shape.index"/>
                </xsl:when>
                <xsl:otherwise>NONE</xsl:otherwise>
            </xsl:choose>
        </xsl:variable>


            <!-- class="{$shape.type} -->
            <xsl:if test="$debug = 1">
                <p style="font-size:8pt;color:red;font-weight:bold;">
                    Shape type: <span style="font-weight:normal"><xsl:value-of select="$isShapeType"/></span>
                    <br/>
                    Shape index: <span style="font-weight:normal"><xsl:value-of select="$shape.index"/></span>
                </p>
            </xsl:if>
            <xsl:apply-templates>
                <xsl:with-param name="shape.type" select="$isShapeType"/>
            </xsl:apply-templates>
    </xsl:template>

    <!-- text-style: caption -->
    <xsl:template match="p:sp[.//a:t]">
        <xsl:param name="shape.type"/>
        <xsl:variable name="lvl" select="a:pPr/@lvl"/>
        <p><xsl:value-of select="."/></p>
    </xsl:template>

    <xsl:template match="p:txBody/a:p[a:r]">
        <xsl:param name="shape.type"/>
        <xsl:variable name="lvl" select="a:pPr/@lvl"/>
        <xsl:choose>
            <xsl:when
                test="
                    (starts-with($shape.type, 'list') and
                    not(.//a:buNone)) or
                    .//a:buChar
                    ">

                <!-- all lists are set to unordered lists -->
                <xsl:if test="not(preceding-sibling::a:p)">
                    <xsl:text disable-output-escaping="yes">&lt;ul></xsl:text>
                </xsl:if>

                <xsl:variable name="current.list.level">
                    <xsl:choose>
                        <xsl:when test="not(a:pPr/@lvl)">0</xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="a:pPr/@lvl"/>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:variable>

                <!-- previous list level -->
                <xsl:variable name="prev.list.level">
                    <xsl:choose>
                        <xsl:when test="not(preceding-sibling::a:p[1]/a:pPr/@lvl)">0</xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="preceding-sibling::a:p[1]/a:pPr/@lvl"/>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:variable>

                <!-- next list level -->
                <xsl:variable name="next.list.level">
                    <xsl:choose>
                        <xsl:when test="not(following-sibling::a:p[1]/a:pPr/@lvl)">0</xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="following-sibling::a:p[1]/a:pPr/@lvl"/>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:variable>

                <!-- Python uses the XSLT 1.0 processor
                     - because of that, it's not possible to use the xsl:for-each-group - syntax
                     - via grouping it's easier possible to create a deep structure from a flat one
                     - here we check each list entry (always on the same structure level)
                      - the difference is the lvl-attribute
                      - writing all necessary <ul><li> elements with xsl:text -->

                <xsl:text disable-output-escaping="yes">&lt;li></xsl:text>

                <xsl:apply-templates/>

                <xsl:if test="$next.list.level > $current.list.level">
                    <xsl:text disable-output-escaping="yes">&lt;ul></xsl:text>
                </xsl:if>
                <xsl:if test="$next.list.level &lt; $current.list.level">
                    <xsl:text disable-output-escaping="yes">&lt;/li>&lt;/ul>&lt;/li></xsl:text>
                </xsl:if>
                <xsl:if test="$next.list.level = $current.list.level">
                    <xsl:text disable-output-escaping="yes">&lt;/li></xsl:text>
                </xsl:if>

                <xsl:if test="not(following-sibling::a:p)">
                    <xsl:text disable-output-escaping="yes">&lt;/ul></xsl:text>
                </xsl:if>
            </xsl:when>
            <xsl:otherwise>
                <xsl:apply-templates>
                    <xsl:with-param name="shape.type" select="$shape.type"/>
                </xsl:apply-templates>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="a:r">
        <xsl:param name="shape.type"/>

        <xsl:variable name="slide.name" select="ancestor::file/@name"/>
        <xsl:variable name="slide.num"
            select="substring-before(substring-after($slide.name, '/slides/slide'), '.xml')"/>
        <xsl:variable name="slide.rels.name" select="concat('_rels/slide', $slide.num, '.xml.rels')"/>
        <xsl:variable name="slide.layout.name"
            select="substring-after($slides.rels[contains(@name, $slide.rels.name)]/*/*/@Target[contains(., 'slideLayouts')], '../')"/>
        <xsl:variable name="slide.layout.node"
            select="$slides.layout[contains(@name, $slide.layout.name)]"/>
        <xsl:variable name="slide.rels.node"
            select="$slides.rels[contains(@name, $slide.rels.name)]"/>

        <xsl:variable name="slide.layout.rels.name"
            select="concat('slideLayouts/_rels/slideLayout', substring-before(substring-after($slide.layout.name, '/slideLayout'), '.xml'), '.xml.rels')"/>
        <xsl:variable name="slide.layout.master.name"
            select="substring-after($slides.layout.rels[contains(@name, $slide.layout.rels.name)]/*/*/@Target[contains(., 'slideMasters')], '../')"/>
        <xsl:variable name="slide.layout.master.node"
            select="$slides.master[contains(@name, $slide.layout.master.name)]"/>

        <!-- information from slide layout or slide master -->
        <xsl:variable name="shape.index" select="ancestor::p:sp//p:ph/@idx"/>

        <xsl:variable name="font-weight">
            <xsl:choose>
                <xsl:when test="a:rPr/@b = 1">bold</xsl:when>
                <xsl:when
                    test="$slide.layout.node//p:sp[.//p:nvPr//p:ph/@idx = $shape.index]//p:txBody//a:lvl1pPr/a:defRPr/@b != ''"
                    >bold</xsl:when>
                <xsl:otherwise>normal</xsl:otherwise>
            </xsl:choose>
        </xsl:variable>


        <xsl:variable name="font-style">
            <xsl:choose>
                <xsl:when test="a:rPr/@i = 1">italic</xsl:when>
                <xsl:when
                    test="$slide.layout.node//p:sp[.//p:nvPr//p:ph/@idx = $shape.index]//p:txBody//a:lvl1pPr/a:defRPr/@i != ''"
                    >italic</xsl:when>
                <xsl:otherwise>normal</xsl:otherwise>
            </xsl:choose>
        </xsl:variable>

        <!-- check fo hyperlink -->
        <xsl:variable name="linkId" select="a:rPr/a:hlinkClick/@r:id"/>

        <xsl:choose>
            <xsl:when test="$linkId != ''">
                <a>
                    <xsl:attribute name="href">
                        <xsl:value-of select="$slide.rels.node/*/*[@Id = $linkId]/@Target"/>
                    </xsl:attribute>
                    <xsl:apply-templates/>
                </a>
            </xsl:when>
            <xsl:otherwise>
                <xsl:apply-templates/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="a:br">
        <br />
    </xsl:template>

    <xsl:template match="p:graphicFrame">
        <xsl:apply-templates select=".//a:tbl"/>
    </xsl:template>

    <!-- tables -->
    <xsl:template match="a:tbl">
        <table class="table">
            <xsl:apply-templates select="a:tr"/>
        </table>
    </xsl:template>

    <!-- table rows -->
    <xsl:template match="a:tr">
        <tr>
            <!-- if a firstRow attribute available, the first row (header) looks a little bit different -->
            <xsl:if test="not(preceding-sibling::a:tr) and parent::a:tbl/a:tblPr/@firstRow = '1'">
                <xsl:attribute name="class">tableRowFirst</xsl:attribute>
            </xsl:if>
            <xsl:apply-templates/>
        </tr>
    </xsl:template>

    <!-- table cells -->
    <xsl:template match="a:tc">
        <td>
            <xsl:apply-templates select=".//a:p[.//a:r]"/>
        </td>
    </xsl:template>

    <!-- Images -->
    <xsl:template match="p:pic">
        <xsl:variable name="slide.name" select="ancestor::file/@name"/>
        <xsl:variable name="slide.num"
            select="substring-before(substring-after($slide.name, '/slides/slide'), '.xml')"/>
        <xsl:variable name="slide.rels.name" select="concat('_rels/slide', $slide.num, '.xml.rels')"/>
        <xsl:variable name="slide.layout.name"
            select="substring-after($slides.rels[contains(@name, $slide.rels.name)]/*/*/@Target, '../')"/>
        <xsl:variable name="slide.layout.node"
            select="$slides.layout[contains(@name, $slide.layout.name)]"/>

        <xsl:variable name="slide.rels.node"
            select="$slides.rels[contains(@name, $slide.rels.name)]"/>

        <!-- id for image in rels file -->
        <xsl:variable name="id" select="descendant::a:blip/@r:embed"/>

        <!-- image filename and description -->
        <div class="image-object">
            <p><strong>Image: </strong>
                <xsl:text> </xsl:text>
                <xsl:value-of
                    select="$slide.rels.node//*[name() = 'Relationship'][@Id = $id]/@Target"/>
                <br />
                <span class="text-muted"><strong>Description: </strong>
                <xsl:value-of select="p:nvPicPr/p:cNvPr/@descr"/></span>
            </p>
        </div>
    </xsl:template>
</xsl:stylesheet>
