<?PHP

# Requires PHP7
# Run with ./vendor/bin/phpunit --bootstrap vendor/autoload.php ToolforgeCommonTest.php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
use PHPUnit\Framework\TestCase;

require_once ( __DIR__ . '/../public_html/php/wikiquery.php' ) ;

/**
 * @covers WikiQuery
 */
final class WikiQueryTest extends TestCase {

	public function test_get_api_base_url() :void {
		$wq = new WikiQuery ( 'de' , 'wikimedia' ) ;
		$this-> assertEquals ( $wq->get_api_base_url() , 'http://de.wikimedia.org/w/api.php?format=php&' ) ;
		$this-> assertEquals ( $wq->get_api_base_url('test') , 'http://de.wikimedia.org/w/api.php?format=php&action=query&prop=test&' ) ;
	}

}

?>
