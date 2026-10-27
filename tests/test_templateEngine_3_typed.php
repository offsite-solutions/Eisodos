<?php
  
  use Eisodos\Eisodos;
  
  require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
  
  $failures = 0;
  
  function check(string $name_, string $expected_, string $actual_): void {
    global $failures;
    if ($expected_ === $actual_) {
      print('PASS ' . $name_ . "\n");
    } else {
      $failures++;
      print('FAIL ' . $name_ . "\n  expected: [" . $expected_ . "]\n  actual:   [" . $actual_ . "]\n");
    }
  }
  
  function checkThrows(string $name_, callable $fn_): void {
    global $failures;
    try {
      $result = $fn_();
      $failures++;
      print('FAIL ' . $name_ . "\n  expected RuntimeException, got: [" . $result . "]\n");
    } catch (RuntimeException) {
      print('PASS ' . $name_ . "\n");
    }
  }
  
  function visitor(string $name_, mixed $value_, bool $cookie_ = false): void {
    Eisodos::$parameterHandler->setParam($name_, $value_, false, $cookie_, $cookie_ ? 'cookie' : 'request');
  }
  
  function render(string $template_): string {
    return Eisodos::$templateEngine->parse($template_);
  }
  
  try {
    Eisodos::getInstance()->init(
      [
        __DIR__,
        'test_templateEngine_3_typed'
      ]
    );
    
    Eisodos::$render->start(
      ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
      [],
      [],
      ''
    );
    
    // := and ~= closing semicolon handling
    check('assign without closing semicolon keeps next char', 'X', render("\$a:='x'X"));
    check('default without closing semicolon keeps next char', 'dX', render("\$empty1~='d'X"));
    check('assign with semicolon', 'X', render("\$a:='x';X"));
    
    // $param[[type,default,protection]] validation
    visitor('currency', 'CHF', true);
    check('regex invalid -> default', 'p_EUR', render('p_$currency[[HUF|EUR|USD,EUR]]'));
    check('default overwrites param', 'EUR', Eisodos::$parameterHandler->getParam('currency'));
    check('cookie flag kept', 'c', Eisodos::$parameterHandler->getParameterArray()['currency']['flag']);
    visitor('currency', 'USD');
    check('regex valid', 'p_USD', render('p_$currency[[HUF|EUR|USD,EUR]]'));
    visitor('currency', "HUF'x");
    check('regex is anchored', 'p_EUR', render('p_$currency[[HUF|EUR|USD,EUR]]'));
    check('empty is valid, no default', 'p_', render('p_$nocurrency[[HUF|EUR|USD,EUR]]'));

    visitor('muid', '12');
    check('numeric valid', 'where muid=12', render('where muid=$muid[[numeric]]'));
    visitor('muid', '1 or 1=1');
    checkThrows('numeric invalid throws', fn() => render('where muid=$muid[[numeric]]'));
    visitor('muid', '-');
    checkThrows('numeric lone minus throws', fn() => render('$muid[[numeric]]'));
    visitor('muid', '-5');
    check('numeric negative', '-5', render('$muid[[numeric]]'));

    visitor('ids', ['1', '2']);
    check('array -> default', '0', render('$ids[[numeric,0]]'));
    visitor('u', "\xC3\x28");
    check('invalid utf8 -> default', 'x', render('$u[[[a-z]+,x]]'));
    visitor('n', '123');
    check('escaped comma in regex', '123', render('$n[[[0-9]{1\,3},0]]'));
    visitor('p', 'a/b');
    check('slash in regex', 'a/b', render('$p[[a/b]]'));

    visitor('arr', 'v');
    check('unclosed rule is untouched', 'v[[0]', render('$arr[[0]'));
    checkThrows('unknown protection throws', fn() => render('$muid[[,,xml]]'));
    checkThrows('broken regex throws', fn() => render('$muid[[(abc]]'));

    Eisodos::$parameterHandler->setParam('.ro', 'X');
    check('readonly: local default', '0', render('$ro[[numeric,0]]'));
    check('readonly: value kept', 'X', Eisodos::$parameterHandler->getParam('ro'));

    check('empty + ~= default', "csopids='DEFAULT'", render("csopids='\$nocsop[[string]]~='DEFAULT';'"));
    visitor('csop', 'abc');
    check('value + ~= skips default', "csopids='abc'", render("csopids='\$csop[[string]]~='DEFAULT';'"));
    check(':= ignores rule', 'Y', render("\$z[[numeric]]:='Y';\$z"));
    check('template_ ignores rule', render('$template_test1embed'), render('$template_test1embed[[numeric]]'));
    check('no rule unchanged', 'abc', render('$csop'));
    
    // control sequence stripping and escaping
    visitor('csop', "a'b");
    check('sql escape', "where csopids='a''b'", render("where csopids='\$csop[[string,,sql]]~='DEFAULT';'"));
    visitor('param', "abc'de");
    check('js escape', "let param='abc\\'de'", render("let param='\$param[[,,javascript]]'"));
    visitor('param', "a\\b\"c\nd</script>\u{2028}");
    check('js escape full', 'a\\\\b\\"c\\nd<\\/script>\\u2028', render('$param[[,,javascript]]'));
    visitor('nev', '<b>"x"</b>');
    check('html escape', '&lt;b&gt;&quot;x&quot;&lt;/b&gt;', render('$nev[[,,html]]'));

    Eisodos::$parameterHandler->setParam('dbpassword', 'SECRET');
    visitor('x', 'a$dbpassword');
    check('dollar stripped', "'adbpassword'", render("'\$x[[,,sql]]'"));
    visitor('nev', '[%funcjob=eq%]');
    check('callback opener stripped', '<div>funcjob=eq%]</div>', render('<div>$nev[[,,html]]</div>'));
    visitor('nev', '[[%%]<<%%>$$a[[::]');
    check('nested openers stripped', ']>a]', render('$nev[[]]'));
    check('nested: no opener left', '0', (string)preg_match('/\$|\[%|<%|\[:|\[#/', render('$nev[[]]')));
    visitor('m', 'bad');
    check('default is not escaped', "it's", render('$m[[HUF|EUR,it\'s,sql]]'));
    
    // review fixes: final-pass markers, value boundaries, anchoring, unclosed rules
    visitor('e', '{#%24dbpassword#}');
    check('urlencode block markers stripped', '<p>%24dbpassword</p>', render('<p>$e[[,,html]]</p>'));
    visitor('e', '_dollar_dbpassword');
    check('_dollar_ marker stripped', 'dbpassword', render('$e[[]]'));
    visitor('x', 'dbpassword');
    checkThrows('template $ before word value throws', fn() => render('US$$x[[,,html]]'));
    visitor('price', '12');
    check('_dollar_ before typed value', 'US_dollar_12', render('US_dollar_$price[[numeric]]'));
    visitor('a', 'q[');
    visitor('b', '%funcjob=eq;x=1%]');
    check('adjacent values do not form [%', "'q[funcjob=eq;x=1%]'", render("'\$a[[,,sql]]\$b[[,,sql]]'"));
    visitor('k', '%funcjob=evil%');
    check('template [ before value', 'arr[funcjob=evil%]', render('arr[$k[[,,javascript]]]'));
    visitor('t', '%FUNC%x%FUNC%');
    check('template < before value', '<FUNC%x%FUNC%>', render('<$t[[,,javascript]]>'));
    visitor('s', 'abc[');
    check('value [ before template %', "like 'abc%'", render("like '\$s[[,,sql]]%'"));
    visitor('w', '#x');
    check('template { before value', '{x', render('{$w[[]]'));
    visitor('c', "HUF\n");
    check('trailing newline fails regex', 'p_EUR', render('p_$c[[HUF|EUR,EUR]]'));
    visitor('muid', "12\n");
    checkThrows('trailing newline fails numeric', fn() => render('$muid[[numeric]]'));
    visitor('arr', 'v');
    check('unclosed rule with later ]] untouched', 'x=v[[0]; y=m[n[1]];', render('x=$arr[[0]; y=m[n[1]];'));
    check('unclosed rule with later [[..]] untouched', 'x=v[[0]; y=1; z=[[2]];', render('x=$arr[[0]; y=1; z=[[2]];'));
    
  } catch (Exception $e) {
    if (!isset(Eisodos::$logger)) {
      die($e->getMessage());
    }
    
    Eisodos::$logger->writeErrorLog($e);
    $failures++;
  }
  
  if ($failures > 0) {
    print($failures . " FAILED\n");
    exit(1);
  }
  print("ALL PASSED\n");
