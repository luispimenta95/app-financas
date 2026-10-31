<?php

namespace App\Services\Transactions;

use App\Exceptions\EncryptedCreditCardStatementException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Throwable;

class CreditCardStatementTextExtractor
{
    public function extract(string $path, ?string $password = null): string
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException('Arquivo da fatura não encontrado.');
        }

        $fromPdftotext = $this->extractWithPdftotext($path, $password);

        if ($fromPdftotext !== null) {
            return $fromPdftotext;
        }

        return $this->extractWithParser($path, $password);
    }

    private function extractWithPdftotext(string $path, ?string $password): ?string
    {
        $binary = $this->pdftotextBinary();

        if ($binary === null) {
            return null;
        }

        $command = [$binary, '-layout', $path, '-'];

        if (filled($password)) {
            array_splice($command, 1, 0, ['-upw', $password]);
        }

        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = mb_strtolower($process->getErrorOutput().$process->getOutput());

            if (str_contains($error, 'password') || str_contains($error, 'encrypted') || str_contains($error, 'incorrect')) {
                throw new EncryptedCreditCardStatementException(
                    filled($password)
                        ? 'A senha da fatura está incorreta.'
                        : 'O PDF da fatura está protegido. Informe a senha usada pelo banco.'
                );
            }

            return null;
        }

        return (string) $process->getOutput();
    }

    private function extractWithParser(string $path, ?string $password): string
    {
        try {
            $config = [];

            if (filled($password)) {
                $config['password'] = $password;
            }

            $text = (new Parser($config))->parseFile($path)->getText();
        } catch (Throwable $exception) {
            $message = mb_strtolower($exception->getMessage());

            if (str_contains($message, 'encrypt') || str_contains($message, 'password') || str_contains($message, 'secured')) {
                throw new EncryptedCreditCardStatementException(
                    filled($password)
                        ? 'A senha da fatura está incorreta.'
                        : 'O PDF da fatura está protegido. Informe a senha usada pelo banco.'
                );
            }

            throw $exception;
        }

        if (trim($text) === '') {
            throw new EncryptedCreditCardStatementException(
                'Não consegui ler o texto do PDF. Se o arquivo tiver senha, informe-a no formulário.'
            );
        }

        return $text;
    }

    private function pdftotextBinary(): ?string
    {
        foreach (['pdftotext', '/usr/bin/pdftotext'] as $binary) {
            if ($binary === 'pdftotext') {
                $process = Process::fromShellCommandline('command -v pdftotext');
                $process->run();

                if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
                    return 'pdftotext';
                }

                continue;
            }

            if (is_executable($binary)) {
                return $binary;
            }
        }

        return null;
    }
}
