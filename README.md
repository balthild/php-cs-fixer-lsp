# PHP CS Fixer Language Server

This project provides a Language Server Protocol (LSP) implementation for PHP CS Fixer. Unlike other existing implementations, this one runs PHP CS Fixer continuously in the background, minimizing the initialization time overhead for each formatting request.

## Installation

If you're using the [vscode extension](https://marketplace.visualstudio.com/items?itemName=balthild.php-cs-fixer-lsp), it will download the language server automatically.

If you need the language server for other editors, download manually from [releases](https://github.com/balthild/php-cs-fixer-lsp/releases), or install with Phive:

```bash
phive install balthild/php-cs-fixer-lsp
```

## Building from Source

Compile to a PHAR file:

```
make build
```

The output will be located at `build` directory.

## Fun Fact

This project itself does not use PHP CS Fixer but [Mago](https://github.com/carthage-software/mago). If you don't need the fine-grained control of PHP CS Fixer, give Mago a try because it's super-fast!
