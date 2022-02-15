<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Wallet;
use App\Repository\WalletRepository;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class NodesSnipingCommand extends Command
{
    protected static $defaultName = 'snipe';

    private const URL = 'https://debank.com/profile/%s/history';
    private const HOST = 'http://localhost:1337';

    private const HISTORY_TABLE_DATA = [
        'main' => '.History_table__9zhFG',
        'line' => '.History_tableLine__3dtlF',
        'line_tx_time' => '.History_sinceTime__3JN2E',
        'line_tx_input' => '.History_ellipsis__rfBNq',
        'line_tx_token' => '.History_tokenChangeItem__3NN7B',
        'line_tx_nft' => '.History_success__3dFwK',
    ];

    private ChatterInterface $chatter;
    private WalletRepository $walletRepository;

    public function __construct(ChatterInterface $chatter, WalletRepository $walletRepository)
    {
        parent::__construct();

        $this->chatter = $chatter;
        $this->walletRepository = $walletRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $wallets = $this->walletRepository->findAllToSnipe();

        while (1) {
            $driver = $this->initChromeDriver();
            foreach ($wallets as $wallet) {
                $url = sprintf(self::URL, $wallet->getAddress());
                $output->writeln('<info>Connecting to '.$url.'...</info>');
                $driver->get($url);

                $output->writeln('<info>Waiting for page to load...</info>');
                sleep(8);

                $lines = $driver->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line']));
                if (count($lines) === 0) {
                    $output->writeln('<error>No transactions found.</error>');
                    $this->chatter->send(new ChatMessage('⚠️ No transactions found for : '.$wallet->getAddress().'( '.$wallet->getName().' )'));

                    return 1;
                }
                $output->writeln('<info>Extracting data...</info>');
                try {
                    $this->extractData($wallet, $output, $lines);
                } catch (\Exception $e) {
                    $this->chatter->send(new ChatMessage('❌ Error occured : '.$e->getMessage()));
                    break;
                }
            }
            $output->writeln('<info>Done!</info>');
            $driver->close();
            sleep(600);
        }

        return 0;
    }

    private function checkForNft(string $txInput): bool
    {
        return stripos($txInput, 'mint') !== false;
    }

    private function checkForNode(string $txInput): bool
    {
        return stripos($txInput, 'node') !== false;
    }

    private function extractData(Wallet $wallet, OutputInterface $output, array $lines): void
    {
        /** @var RemoteWebElement $line */
        foreach ($lines as $line) {
            // Get the time of the last transaction and see if we have it already
            $txTime = $line->findElement(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_time']))->getText();

            // Get subject of the transaction if there is one
            try {
                $txInput = $line->findElement(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_input']))->getText();
            } catch (\Exception $e) {
                continue;
            }

            // if there is no node or nft mint in the transaction, skip it
            $hasNft = $this->checkForNft($txInput);
            $hasNode = $this->checkForNode($txInput);
            if (!$hasNode && !$hasNft) {
                continue;
            }

            // Handle nodes or nfts with different processing
            if ($hasNft && !$hasNode) {
                $nfts = $wallet->getNfts();
                try {
                    $txToken = $line->findElement(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_nft']))->getAttribute('title');
                } catch (\Exception $e) {
                    continue;
                }
                $regex = '/(\w+).*(\d+)/';
                $matches = [];
                preg_match($regex, $txToken, $matches);
                if (!array_key_exists(1, $matches)) {
                    var_dump('WRONG REGEX');
                    var_dump($txToken);
                    var_dump($matches);
                    continue;
                }
                if (!in_array($matches[1], $nfts)) {
                    $this->chatter->send(new ChatMessage('🖼️ New NFT minted  ('.$wallet->getName().') : '.$txToken.' at '.$txTime));
                    $wallet->addNft($matches[1]);
                    $this->walletRepository->persist($wallet);
                }
            }

            if ($hasNode && !$hasNft) {
                $txToken = $line->findElements(WebDriverBy::cssSelector(self::HISTORY_TABLE_DATA['line_tx_token']));
                if (!array_key_exists(0, $txToken)) {
                    continue;
                }
                $txToken = array_key_exists(1, $txToken) ? $txToken[1]->getAttribute('title') : $txToken[0]->getAttribute('title');
                $nodes = $wallet->getNodes();
                if (!in_array($txToken, $nodes)) {
                    $this->chatter->send(new ChatMessage('💰 New node added  ('.$wallet->getName().') : '.$txToken.' at '.$txTime));
                    $wallet->addNode($txToken);
                    $this->walletRepository->persist($wallet);
                }
            }
        }

        $this->walletRepository->flush();
    }

    private function initChromeDriver(): RemoteWebDriver
    {
        $capabilities = DesiredCapabilities::chrome();
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments(['--headless']);
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

        return RemoteWebDriver::create(self::HOST, $capabilities);
    }
}
