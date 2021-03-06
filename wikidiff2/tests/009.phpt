--TEST--
Test case for T197157 from https://sv.wikipedia.org/wiki/Special:Diff/43235099
--FILE--
<?php
$before = <<<EOT
== Priser & Utmärkelser ==
3: 1

4: 1

5: 1

6: 1
EOT;

#---------------------------------------------------

$after = <<<EOT
== Priser & Utmärkelser ==
*3: 1
*4: 1
*5: 1
*6: 1
EOT;

#---------------------------------------------------

print wikidiff2_do_diff( $before, $after, 2 );

?>
--EXPECT--
<tr>
  <td colspan="2" class="diff-lineno"><!--LINE 1--></td>
  <td colspan="2" class="diff-lineno"><!--LINE 1--></td>
</tr>
<tr>
  <td class="diff-marker">&#160;</td>
  <td class="diff-context"><div>== Priser &amp; Utmärkelser ==</div></td>
  <td class="diff-marker">&#160;</td>
  <td class="diff-context"><div>== Priser &amp; Utmärkelser ==</div></td>
</tr>
<tr>
  <td class="diff-marker">−</td>
  <td class="diff-deletedline"><div>3: 1</div></td>
  <td class="diff-marker">+</td>
  <td class="diff-addedline"><div><ins class="diffchange diffchange-inline">*</ins>3: 1</div></td>
</tr>
<tr>
  <td colspan="2" class="diff-empty">&#160;</td>
  <td class="diff-marker"><a class="mw-diff-movedpara-right" href="#movedpara_7_0_lhs">&#x26AB;</a></td>
  <td class="diff-addedline"><div><a name="movedpara_2_0_rhs"></a><ins class="diffchange diffchange-inline">*4</ins>: 1</div></td>
</tr>
<tr>
  <td class="diff-marker">−</td>
  <td class="diff-deletedline"></td>
  <td colspan="2" class="diff-empty">&#160;</td>
</tr>
<tr>
  <td class="diff-marker">−</td>
  <td class="diff-deletedline"><div><del class="diffchange diffchange-inline">4</del>: 1</div></td>
  <td class="diff-marker">+</td>
  <td class="diff-addedline"><div><ins class="diffchange diffchange-inline">*5</ins>: 1</div></td>
</tr>
<tr>
  <td colspan="2" class="diff-empty">&#160;</td>
  <td class="diff-marker"><a class="mw-diff-movedpara-right" href="#movedpara_7_2_lhs">&#x26AB;</a></td>
  <td class="diff-addedline"><div><a name="movedpara_5_0_rhs"></a><ins class="diffchange diffchange-inline">*</ins>6: 1</div></td>
</tr>
<tr>
  <td class="diff-marker">−</td>
  <td class="diff-deletedline"></td>
  <td colspan="2" class="diff-empty">&#160;</td>
</tr>
<tr>
  <td class="diff-marker"><a class="mw-diff-movedpara-left" href="#movedpara_2_0_rhs">&#x26AB;</a></td>
  <td class="diff-deletedline"><div><a name="movedpara_7_0_lhs"></a><del class="diffchange diffchange-inline">5</del>: 1</div></td>
  <td colspan="2" class="diff-empty">&#160;</td>
</tr>
<tr>
  <td class="diff-marker">−</td>
  <td class="diff-deletedline"></td>
  <td colspan="2" class="diff-empty">&#160;</td>
</tr>
<tr>
  <td class="diff-marker"><a class="mw-diff-movedpara-left" href="#movedpara_5_0_rhs">&#x26AB;</a></td>
  <td class="diff-deletedline"><div><a name="movedpara_7_2_lhs"></a>6: 1</div></td>
  <td colspan="2" class="diff-empty">&#160;</td>
</tr>
