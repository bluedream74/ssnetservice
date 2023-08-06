<?php

namespace App\Console\Commands;

use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Support\Facades\Cookie;
use Exception;

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $options = new ChromeOptions();
        $options->addArguments(["--headless","--disable-gpu", "--no-sandbox"]);

        $caps = DesiredCapabilities::chrome();
	      $caps->setCapability('acceptSslCerts', false);
        $caps->setCapability(ChromeOptions::CAPABILITY, $options);
        $caps->setPlatform("Linux");
        $serverUrl = 'http://localhost:4444';
		
        $driver = RemoteWebDriver::create($serverUrl, $caps,5000);
	      
        $driver->get('https://en.wikipedia.org/wiki/Selenium_(software)');

        // write 'PHP' in the search box
        $driver->findElement(WebDriverBy::id('searchInput')) // find search input element
            ->sendKeys('PHP') // fill the search box
            ->submit(); // submit the whole form

        // wait until 'PHP' is shown in the page heading element
        $driver->wait()->until(
            WebDriverExpectedCondition::elementTextContains(WebDriverBy::id('firstHeading'), 'PHP')
        );

        // print title of the current page to output
        echo "The title is '" . $driver->getTitle() . "'\n";

        // print URL of current page to output
        echo "The current URL is '" . $driver->getCurrentURL() . "'\n";

        // find element of 'History' item in menu
        $historyButton = $driver->findElement(
            WebDriverBy::cssSelector('#ca-history a')
        );

        // read text of the element and print it to output
        echo "About to click to button with text: '" . $historyButton->getText() . "'\n";

        // click the element to navigate to revision history page
        $historyButton->click();

        // wait until the target page is loaded
        $driver->wait()->until(
            WebDriverExpectedCondition::titleContains('Revision history')
        );

        // print the title of the current page
        echo "The title is '" . $driver->getTitle() . "'\n";

        // print the URI of the current page

        echo "The current URI is '" . $driver->getCurrentURL() . "'\n";

        // delete all cookies
        $cookies = $driver->manage()->getCookies();
        print_r($cookies);
        $driver->manage()->deleteAllCookies();



        // dump current cookies to output



        $driver->quit();
        
        return 0;
    }
   
}
