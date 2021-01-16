# Mercado Pago Split de Pagamento (WooCommerce + WCFM) / Split Payment
Hack do plugin do Mercado Pago para WooCommerce 4.6.0 (https://github.com/mercadopago/cart-woocommerce/releases/tag/v4.6.0) para possibilitar split de pagamento com o plugin de marketplace "WCFM Marketplace – Best Multivendor Marketplace for WooCommerce" (https://wordpress.org/plugins/wc-multivendor-marketplace/)

## Pontos de atenção!!!!
O hack foi feito para suprir necessidades bem específicas de um projeto. Ao usar em outros projetos você pode ter problemas que não vou resolver :) Teste e adapte o código como você quiser. Se encontrar oportunidades de melhorias, avisa! Talvez, tendo tempo, eu possa organizar tudo para uso geral, mas não espere por isso...

- este plugin não respeita todas as configurações do marketplace, apenas calcula a comissão de acordo com a comissão global e inclui ou não o frete na comissão do marketplace
- o processo de criar usuários de teste (se vc precisar) é uma gambiarra no arquivo /woocommerce-mercadopago-split.php

TESTE MUITO BEM SEU MARKETPLACE, COM PRODUTOS MUITO BARATOS (R$ 1,00) PRIMEIRO, ANTES DE COLOCAR EM PRODUÇÃO E TER PROBLEMAS COM DINHEIRO DOS LOJISTAS!

## Instalação
1. Baixe um arquivo .zip desse plugin, aqui no GitHub no botão verde "Code" -> "Download ZIP";
2. Na administração do WordPress, acesse o menu "Plugins" -> "Adicionar novo";
3. Na página "Instalar plugins", clique no botão "Enviar plugin" (no topo, ao lado do título da página);
4. Envie o arquivo .zip e ative o plugin.

## Configuração
1. Na administração do WordPress, acesse o menu "WooCommerce" -> "Configurações";
2. Crie uma página para o botão de vincular contas e cole o shortcode: [wcmps_integrar_contas];
3. Copie e reserve a URL dessa nova página para informar no passo 7 da criação do app.
4. Acesse a aba "Pagamento", ative e configure o plugin. Será necessário criar o app no Mercado Pago antes de configurar;

## Criação de app no Mercado Pago
É necessário criar o app no Mercado Pago. Siga esses passos:
1. acesse sua conta em mercadopago.com.br;
2. no menu lateral, acesse "Seu negócio" -> "Configurações";
3. role a página até o fim e acesse "Credenciais";
4. na barra cinza, com um app já criado, clique nos 3 pontos à direita e em "Criar nova aplicação";
5. clique no botão "Criar nova aplicação";
6. na primeira tela, preencha as informações;
7. na segunda tela, marque MP Connect / Marketplace mode, e informe a "Redirect URL" usando o endereço copiado no item 3 da *Configuração* (tópico anterior). Deixe os outros itens como estão e clique em "Criar aplicativo".

## Dicas
- a configuração do percentual de comissão do Marketplace é configurado no plugin WCFM Marketplace. *Atenção:* nesse hack só é possível esse formato de comissão;
- para alterar o código do plugin, acesse o commit "Hack concluído" (https://github.com/alexlana/woocommerce-mercadopago-split/commit/d9c2509de3b2f322809b8f465804711e07493e36). Nessa página você consegue ver os principais pontos onde o plugin foi alterado para saber por onde começar. Mas note que houve commits posteriores;
