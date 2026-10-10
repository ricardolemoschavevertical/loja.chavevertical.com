# Campanhas da Homepage — Chave Vertical

Este ficheiro define como criar ou alterar campanhas da homepage diretamente pelo GitHub.

## Fonte de configuração

Editar:

`wp-content/themes/chavevertical-lite/config/homepage-highlights.json`

Schema de validação:

`wp-content/themes/chavevertical-lite/config/homepage-highlights.schema.json`

O tema aceita duas origens:
- `github`: usa o JSON do repositório.
- `admin`: usa os valores guardados em **WooCommerce → Destaques Homepage**.

## Regra principal para campanhas

Sempre que o pedido for “criar uma campanha no Hero” ou “criar um Destaque”, a alteração deve ser feita **apenas no ficheiro de configuração** quando os campos existentes forem suficientes.

Não alterar templates PHP/CSS para uma campanha normal.

### Campos visuais

- `image_url`: URL absoluta HTTPS ou caminho `theme://...`.
- `image_id`: ID da Media Library quando configurado no Admin.
- `full_image: true`: a imagem ocupa 100% da área do bloco.
- `show_text: true`: mostra texto sobre a imagem.
- `show_text: false`: mostra apenas a imagem.
- `background`: fundo usado quando não existe imagem.
- `text_color`: cor do texto.
- `accent_color`: cor de destaque no Hero.
- `url`: destino do clique.

### Imagens — regra para não cortar

Para campanhas de imagem integral, preparar a imagem já na proporção do espaço final:

| Área | Tamanho recomendado | Proporção |
| --- | ---: | ---: |
| Hero principal | 1293×422 px | ≈3,06:1 |
| Caixa lateral | 800×800 px | 1:1 |
| Destaques | 800×800 px | 1:1 |

Preferir **WEBP**.

As imagens integrais usam `object-fit: contain` para evitar cortes. Quando a proporção da imagem corresponde à do bloco, este fica totalmente preenchido; se diferir, pode ficar visível o fundo configurado no plugin. Em desktop, a caixa lateral ocupa exatamente uma das quatro colunas dos Destaques, e o Hero principal ajusta-se às outras três.

Não guardar campanhas como Base64 no repositório. Usar:
1. URL do CDN / R2 / `imagens.chavevertical.com`;
2. Media Library pelo Admin;
3. `theme://...` apenas para assets permanentes e válidos do tema.

## Hero principal

Exemplo — imagem total com texto:

```json
{
  "eyebrow": "CAMPANHA",
  "title": "Título principal",
  "title_accent": "Linha de destaque",
  "description": "Descrição curta.",
  "cta": "VER CAMPANHA",
  "url": "/categoria-produto/exemplo/",
  "background": "#101820",
  "text_color": "#ffffff",
  "accent_color": "#ffd21f",
  "image_id": 0,
  "image_url": "https://imagens.chavevertical.com/campanhas/campanha.webp",
  "full_image": true,
  "show_text": true
}
```

Exemplo — imagem total sem texto:

```json
{
  "title": "",
  "description": "",
  "cta": "",
  "url": "/categoria-produto/exemplo/",
  "image_id": 0,
  "image_url": "https://imagens.chavevertical.com/campanhas/campanha.webp",
  "full_image": true,
  "show_text": false
}
```

## Caixa lateral

Usar os mesmos campos do Hero.

Para imagem integral com texto:
- `full_image: true`
- `show_text: true`

Para arte final que já contém todo o texto:
- `full_image: true`
- `show_text: false`

## Destaques

Existem sempre **4 Destaques** no array `highlights`.

Exemplo:

```json
{
  "eyebrow": "OFERTA",
  "title": "Equipamento profissional",
  "description": "Descrição curta da campanha.",
  "cta": "VER PRODUTOS →",
  "url": "/?s=campanha&post_type=product",
  "background": "#101820",
  "text_color": "#ffffff",
  "image_id": 0,
  "image_url": "https://imagens.chavevertical.com/campanhas/destaque.webp",
  "full_image": true,
  "show_text": true,
  "rotation_image_ids": [],
  "rotation_image_urls": [],
  "rotation_seconds": 5
}
```

## Rotação de imagens nos Destaques

A imagem principal aparece primeiro.

Imagens adicionais podem ser definidas por:

`rotation_image_ids`

ou

`rotation_image_urls`

Exemplo:

```json
{
  "image_url": "https://imagens.chavevertical.com/campanhas/01.webp",
  "rotation_image_urls": [
    "https://imagens.chavevertical.com/campanhas/02.webp",
    "https://imagens.chavevertical.com/campanhas/03.webp"
  ],
  "rotation_seconds": 5,
  "full_image": true,
  "show_text": false
}
```

## Regras para alterações feitas por agente/GitHub

Quando for pedido para criar uma campanha:

1. Ler primeiro o estado atual de `homepage-highlights.json`.
2. Alterar apenas o bloco pedido.
3. Preservar os restantes Hero/Destaques.
4. Usar `full_image: true` quando a campanha deve ocupar toda a área.
5. Usar `show_text: false` quando a própria arte já contém o texto.
6. Usar `show_text: true` quando o texto deve ser gerido pelo site.
7. Respeitar as proporções de imagem indicadas acima.
8. Preferir WEBP e URLs do CDN.
9. Nunca incorporar Base64 como imagem de campanha.
10. Validar que o JSON continua válido antes de fazer commit.
11. Não alterar links, cores ou textos de outros blocos sem instrução.
12. Não remover o array de 4 Destaques.

## Admin WordPress

Em **WooCommerce → Destaques Homepage**:

- cada Hero/Destaque apresenta uma moldura com a proporção recomendada;
- clicar na própria moldura abre a Biblioteca Multimédia;
- é possível escolher **Imagem a ocupar todo o espaço**;
- é possível ligar/desligar **Mostrar texto sobre a imagem**;
- o preview simula o recorte final usando a proporção do respetivo bloco.

Para minimizar cortes, carregar imagens já preparadas com as dimensões recomendadas.
