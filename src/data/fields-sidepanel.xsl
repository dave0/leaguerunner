<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:template match="overlay">
    <table>
    <tr><td class='label' style='text-align: left; border-bottom: 1px solid blue'>Field Sites</td></tr>
    <xsl:apply-templates select="location"/>
    </table>
  </xsl:template>
  
  <xsl:template match="location">
    <tr style="cursor:pointer" onclick="showLocationInfo(this.id)">
    <xsl:attribute name="id">
      <xsl:value-of select="@id"/>
    </xsl:attribute>
    <td>
    <xsl:if test="info/title">
      <xsl:attribute name="class">label</xsl:attribute>
      <div>
      <xsl:choose>
        <xsl:when test="icon/@class != 'noicon'">
          <a href="javascript:void(0)" onclick="this.blur()">
            <xsl:attribute name="style">color: #0000cc</xsl:attribute>
            <xsl:copy-of select="info/title/node()"/>
          </a>
        </xsl:when>
        <xsl:otherwise>
          <xsl:attribute name="style">font-weight: bold</xsl:attribute>
          <xsl:copy-of select="info/title/node()"/>
        </xsl:otherwise>
      </xsl:choose>
      </div>
    </xsl:if>
    </td>
    </tr>
  </xsl:template>
  
</xsl:stylesheet>
