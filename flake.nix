{
  inputs = {
    flake-parts.url = "github:hercules-ci/flake-parts";
    nixpkgs.url = "github:cachix/devenv-nixpkgs/rolling";
    devenv.url = "github:cachix/devenv";
  };

  nixConfig = {
    extra-trusted-public-keys = "devenv.cachix.org-1:w1cLUi8dv3hnoSPGAuibQv+f9TZLr6cv/Hm9XgU50cw=";
    extra-substituters = "https://devenv.cachix.org";
  };

  outputs = inputs@{ flake-parts, nixpkgs, ... }:
    flake-parts.lib.mkFlake { inherit inputs; } {
      imports = [ inputs.devenv.flakeModule ];
      systems = nixpkgs.lib.systems.flakeExposed;

      perSystem = { pkgs, config, ... }: {
        packages = {
          box = pkgs.php84Packages.box.override {
            php82 = pkgs.php84;
          };
        };

        devenv.shells.default = {
          name = "php-cs-fixer-lsp";

          packages = [ config.packages.box ];

          languages.php = {
            enable = true;
            package = pkgs.php84.buildEnv {
              extensions = { all, enabled }: with all; enabled ++ [
                xdebug
              ];
              extraConfig = ''
                xdebug.mode = debug
              '';
            };
          };
        };
      };
    };
}
