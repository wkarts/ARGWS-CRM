#!/usr/bin/env python3
"""Build the PT-BR MA catalogue by changing text nodes only, never identifiers.
Local generation/QA helper; no network, SQL, mail, or production operations.
"""
from pathlib import Path
from html.parser import HTMLParser
from html import unescape, escape
from collections import Counter
import copy, json, re, hashlib

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'modules/ma/resources/email_templates_ptbr.json'

# Exact normalized visible node -> reviewed Brazilian Portuguese text.
TRANSLATIONS = {
    'RUNNING': 'CORRENDO',
    'ON HOPE': 'COM ESPERANÇA',
    "Let's Start": 'Vamos começar',
    'H E L L O': 'O L Á',
    'T H E R E': 'Q U E R I D A',
    "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since": 'Este é apenas um texto de exemplo usado na indústria gráfica e de editoração. Textos como este são utilizados há muito tempo para demonstrar a diagramação',
    'Read More': 'Leia mais',
    'Lorem Ipsum is simply dummy text of the printing and typesetting industry.': 'Este é apenas um texto de exemplo usado na indústria gráfica e de editoração.',
    'R e a d M o r e >>': 'L e i a M a i s >>',
    '" Lorem Ipsum is simply dummy text of the': '" Este é apenas um texto de exemplo usado na',
    'printing and typesetting industry. "': 'indústria gráfica e de editoração. "',
    'See our tribute to Gabe Grunewald': 'Veja nossa homenagem a Gabe Grunewald',
    'This email was sent to hellofitness.yeah by Goop Inc.': 'Este e-mail foi enviado para hellofitness.yeah por Goop Inc.',
    'Fitness Inc. All rights reserved.': 'Fitness Inc. Todos os direitos reservados.',
    'Company No. 123456789': 'Registro da empresa nº 123456789',
    'Manage Preferences': 'Gerenciar preferências',
    'Unsubscribe': 'Cancelar inscrição',
    'View in browser': 'Ver no navegador',
    'LOGO': 'LOGOTIPO',
    'Huge Range': 'Grande variedade',
    'of Trendy': 'de roupas',
    'Clothing': 'da moda',
    'S H O P N O W': 'C O M P R E A G O R A',
    '" Keeping it': '" Mantendo a',
    'simple "': 'simplicidade "',
    'Lorem ipsum dolor sit amet, consectetur adipiscing elit,': 'Este é um texto de exemplo para apresentar o conteúdo,',
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.': 'mostrar a diagramação e demonstrar como as informações serão exibidas.',
    'R E A D M O R E': 'L E I A M A I S',
    'UP TO': 'ATÉ',
    '% OFF': '% DE DESCONTO',
    'L o r e m i p s u m d o l o r': 'T e x t o d e e x e m p l o',
    '" Lorem ipsum dolor sit amet, consectetur adipiscing elit,': '" Este é um texto de exemplo para apresentar o conteúdo,',
    'Ut enim ad minim veniam "': 'com uma demonstração da diagramação "',
    'A Simple Guide to': 'Um guia simples de',
    'Minimalist S': 'estilo mini',
    'tyle.': 'malista.',
    'F O L L O W A L O N G W I T H U S': 'A C O M P A N H E - N O S',
    'Cloth Inc. All rights reserved.': 'Cloth Inc. Todos os direitos reservados.',
    'Manage Preferences | Unsubscribe | View in browser': 'Gerenciar preferências | Cancelar inscrição | Ver no navegador',
    'Too many emails?': 'E-mails demais?',
    'DO NOT RUSH': 'VÁ COM CALMA',
    'CATCH TIME': 'APROVEITE O TEMPO',
    'NOW!': 'AGORA!',
    'GO': 'IR',
    'HOME': 'INÍCIO',
    'MAN': 'MASCULINO',
    'WOMAN': 'FEMININO',
    'WHY DO WE': 'POR QUE',
    'USE IT?': 'USAMOS ISSO?',
    'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in.': 'Este é um texto de exemplo para apresentar o conteúdo e demonstrar sua diagramação. Ele permite visualizar o espaço ocupado pelas informações, a distribuição das linhas e o equilíbrio entre os elementos. Substitua este trecho pelo texto que deseja compartilhar com seu público.',
    'SPECIAL OFFER': 'OFERTA ESPECIAL',
    'BEST SALE': 'MELHOR PROMOÇÃO',
    'FROM OUR STORE': 'DA NOSSA LOJA',
    'OUTERWEAR': 'AGASALHOS',
    'BLACK COAT': 'CASACO PRETO',
    'BUY NOW': 'COMPRE AGORA',
    'SHOES': 'CALÇADOS',
    'BROWN BOOTS': 'BOTAS MARRONS',
    'BROWN JACKET': 'JAQUETA MARROM',
    'HATS': 'CHAPÉUS',
    'A CAP': 'UM BONÉ',
    'WHERE DOES IT COME FROM?': 'DE ONDE ISSO VEM?',
    "Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.": 'Esse texto de exemplo é usado como padrão na indústria desde os anos 1500, quando um impressor desconhecido reuniu tipos e os reorganizou para criar um livro de amostras tipográficas.',
    'MORE SPECIAL OFFERS': 'MAIS OFERTAS ESPECIAIS',
    'AUTMN COLLECTION': 'COLEÇÃO DE OUTONO',
    'CASUAL STYLE': 'ESTILO CASUAL',
    'Lorem Ipsum is simply dummy text of the printing and typesetting industry. here are many variations of passages.': 'Este é apenas um texto de exemplo usado na indústria gráfica e de editoração. Existem diversas variações desses trechos.',
    'READ MORE': 'LEIA MAIS',
    'COMBINING STYLES': 'COMBINANDO ESTILOS',
    'SHOW MORE POST': 'VER MAIS PUBLICAÇÕES',
    '1914 TRANSLATION BY H. RACKHAM': 'TRADUÇÃO DE 1914 POR H. RACKHAM',
    'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.': 'Este é um texto de exemplo para apresentar o conteúdo e demonstrar sua diagramação. Ele permite visualizar o espaço ocupado pelas informações e a distribuição das linhas. Use este espaço para desenvolver sua mensagem, destacar os pontos mais importantes e explicar os detalhes que deseja compartilhar com seu público.',
    'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation': 'Este é um texto de exemplo para apresentar o conteúdo e demonstrar sua diagramação. Use este espaço para organizar as informações e compartilhar uma mensagem com seu público',
    '© Fashion | All Rights Reserve.': '© Fashion | Todos os direitos reservados.',
    'here': 'aqui',
    'TH': '',
    'NOVEMBER': 'DE NOVEMBRO',
    'START FROM': 'A PARTIR DE',
    '40% OFF': '40% DE DESCONTO',
    'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium,': 'Este é um texto de exemplo para apresentar as informações e demonstrar a diagramação do conteúdo,',
    'totam rem': 'com os detalhes',
    'aperiam, eaque ipsa quae ab illo': 'organizados para facilitar a leitura e dar',
    'inventore': 'destaque',
    'veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia': 'aos pontos mais importantes da mensagem. Este espaço pode ser adaptado para apresentar',
    'voluptas sit aspernatur': 'as informações desejadas',
    'aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet.': 'e explicar os detalhes de forma clara para seu público. Substitua este texto pelo conteúdo final, mantendo uma leitura agradável e uma apresentação bem organizada.',
    'You received this email because you signed up for My Company Inc': 'Você recebeu este e-mail porque se cadastrou na Minha Empresa Inc',
    'Thanks for being with us!': 'Obrigado por estar com a gente!',
    'Hi,': 'Olá,',
    'Thank you again for choosing us for your lorem ipsum dolor.': 'Agradecemos novamente por nos escolher para atender às suas necessidades.',
    'If you were happy with the efficiency of the service could I ask that you leave us some brief feedback on your experience via the link below.': 'Se você ficou satisfeito com a eficiência do serviço, pedimos que deixe uma breve avaliação sobre sua experiência pelo link abaixo.',
    'Review us on Google': 'Avalie-nos no Google',
    'Look forward to hearing from you': 'Esperamos receber sua avaliação',
    'in the near future.': 'em breve.',
    'Call us: 111 222 333': 'Ligue para nós: 111 222 333',
    'EMAIL: info@thecompany.com': 'E-MAIL: info@thecompany.com',
    'Address line / Full contact addreess & post code goes here': 'Endereço / Insira aqui o endereço completo para contato e o código postal',
    '© Shop Name | All Rights Reserved': '© Nome da Loja | Todos os direitos reservados',
    'Happy Birthday!': 'Feliz aniversário!',
    'We’re going to help you celebrate.': 'Vamos ajudar você a comemorar.',
    'Your birthday comes around only once a year,': 'Seu aniversário acontece só uma vez por ano,',
    'so let’s give it the attention it deserves.': 'então vamos dar a ele a atenção que merece.',
    'As our gift to you, enjoy 50% off any product': 'Nosso presente para você: 50% de desconto em qualquer linha',
    'line for the next 30 days.': 'de produtos pelos próximos 30 dias.',
    'That’s right! It’s not just your birthday,': 'Isso mesmo! Seu aniversário é só o começo,',
    'but we’re celebrating you for a whole month!': 'vamos celebrar você durante um mês inteiro!',
    'PICK YOUR GIFT NOW!': 'ESCOLHA SEU PRESENTE AGORA!',
    'We hope you have the happiest of birthdays!': 'Desejamos a você um aniversário muito feliz!',
    'Here’s to you,': 'Um brinde a você,',
    'The Your Company Team': 'A equipe da Sua Empresa',
    'You received this email because you signed up for My Company Inc.': 'Você recebeu este e-mail porque se cadastrou na Minha Empresa Inc.',
    'An explanation of': 'Uma explicação sobre',
    'B R A N D I N G': 'G E S T Ã O D E M A R C A',
    'Dear Name,': 'Olá, Nome!',
    'At brandingcompany, we get asked a lot, “What is branding?”': 'Na brandingcompany, ouvimos muito a pergunta: “O que é gestão de marca?”',
    'Once you understand it, it’s a simple idea, but it’s powerful in its effects on your business.': 'Quando você entende o conceito, a ideia é simples, mas seus efeitos no seu negócio são poderosos.',
    'When you’re building your brand, you need to approach it step-by-step.': 'Ao construir sua marca, você precisa avançar passo a passo.',
    'At BrandSpecialist, we start with a six-step process that guides our work:': 'Na BrandSpecialist, começamos com um processo de seis etapas que orienta nosso trabalho:',
    'Design': 'Criação visual',
    'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh': 'Este é um texto de exemplo para apresentar o conteúdo e demonstrar sua diagramação',
    'Strategy': 'Estratégia',
    'Social Media': 'Redes sociais',
    'Brand Material': 'Materiais da marca',
    'Webste': 'Site',
    'There are infinite steps in between, but these are the BIG steps that we use to make sure we’re creating everything you need.': 'Há inúmeras etapas intermediárias, mas essas são as PRINCIPAIS etapas que seguimos para garantir a criação de tudo o que você precisa.',
    'If you’re ready, BraningCompany can help you put it all together.': 'Se você está pronto, a BraningCompany pode ajudar a reunir tudo isso.',
    'Reach out to BrandCompany': 'Fale com a BrandCompany',
    'Talk to you soon.': 'Até breve.',
    'Get In Touch': 'Entre em contato',
    'Talk to us (111) 222-3333 or': 'Fale com a gente: (111) 222-3333 ou',
    'Email us hello@brandingCompany.com': 'Envie um e-mail para hello@brandingCompany.com',
    '© Branding Company. All Rights Reserved': '© Branding Company. Todos os direitos reservados',
    'Image': 'Imagem',
    'Video': 'Vídeo',
}
# Names, unchanged contact/address/domain data, and current Brazilian usage.
PRESERVED = {
    'A N N I E', '212 26th St | Santa Monica | CA | 90402',
    'BLACK FRIDAY', 'BLACK', 'FRIDAY', 'Marketing', 'WWW.COMPANYNAME.COM',
    'Facebook', 'Pinterest', 'LinkedIn', 'Twitter', 'Instagram', 'YouTube', 'SoundCloud', 'WhatsApp',
}
NAMES = ['Condicionamento físico', 'Promoção de Dia dos Namorados',
         'Oferta de Black Friday', 'Promoção de Black Friday',
         'Agradecimento ao assinante', 'Feliz aniversário', 'Gestão de marca']


def normalize(text):
    return re.sub(r'\s+', ' ', unescape(text)).strip()


class Nodes(HTMLParser):
    """Locate text/attribute byte spans without serializing or rewriting markup."""
    def __init__(self, source):
        super().__init__(convert_charrefs=False)
        self.source = source
        self.offsets = [0]
        for m in re.finditer('\n', source):
            self.offsets.append(m.end())
        self.skip = []
        self.ranges = []
        self.attrs = []
        self.tags = []
        self.html_tag_ends = []
        self.feed(source)
        self.close()

    def source_offset(self):
        line, col = self.getpos()
        return self.offsets[line - 1] + col

    def record(self, start, end):
        if self.skip:
            return
        if self.ranges and self.ranges[-1][1] == start:
            self.ranges[-1] = (self.ranges[-1][0], end)
        else:
            self.ranges.append((start, end))

    def handle_starttag(self, tag, attrs):
        raw = self.get_starttag_text()
        off = self.source_offset()
        self.tags.append(('start', tag, attrs))
        if tag in ('style', 'script', 'head'):
            self.skip.append(tag)
        for m in re.finditer(r'(?P<key>\b(?:alt|title|lang|xml:lang))\s*=\s*(?P<q>[\"\'])(?P<val>.*?)(?P=q)', raw, re.I | re.S):
            self.attrs.append((off + m.start('val'), off + m.end('val'), m['key'].lower(), m['val']))
        if tag == 'html' and not any(k == 'lang' for k, _ in attrs):
            self.html_tag_ends.append(off + len(raw) - 1)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if self.skip and self.skip[-1] == tag:
            self.skip.pop()

    def handle_endtag(self, tag):
        self.tags.append(('end', tag, None))
        if self.skip and self.skip[-1] == tag:
            self.skip.pop()

    def handle_data(self, data):
        self.record(self.source_offset(), self.source_offset() + len(data))

    def handle_entityref(self, name):
        off = self.source_offset()
        size = 1 + len(name)
        if self.source[off + size:off + size + 1] == ';':
            size += 1
        self.record(off, off + size)

    def handle_charref(self, name):
        off = self.source_offset()
        size = 2 + len(name)
        if self.source[off + size:off + size + 1] == ';':
            size += 1
        self.record(off, off + size)


def translate_text(raw, seen):
    key = normalize(raw)
    if not key or not any(c.isalpha() for c in key):
        return raw
    if key in PRESERVED:
        return raw
    if key not in TRANSLATIONS:
        raise AssertionError('Unmapped visible text: ' + repr(key))
    seen[key] += 1
    # Preserve original outer spacing, including non-breaking-space entities.
    ws = r'(?:\s|&nbsp;|&#160;|&#x[aA]0;)'
    lead = re.match(ws + '*', raw).group()
    trail = re.search(ws + '*$', raw).group()
    target = escape(TRANSLATIONS[key], quote=False)
    # Retain the original hand-spaced call-to-action's nonbreaking separator.
    if key == 'R e a d M o r e >>':
        target = 'L e i a&nbsp; &nbsp;M a i s &gt;&gt;'
    return lead + target + trail


def translate_html(source, seen, add_language=False):
    nodes = Nodes(source)
    patches = []
    for start, end in nodes.ranges:
        value = translate_text(source[start:end], seen)
        if value != source[start:end]:
            patches.append((start, end, value))
    for start, end, name, raw in nodes.attrs:
        value = 'pt-BR' if name in ('lang', 'xml:lang') else translate_text(raw, seen)
        if value != raw:
            patches.append((start, end, value))
    if add_language:
        patches += [(i, i, ' lang="pt-BR"') for i in nodes.html_tag_ends]
    for start, end, value in sorted(patches, reverse=True):
        source = source[:start] + value + source[end:]
    return source


def translate_design(value, seen, path=()):
    if isinstance(value, dict):
        return {k: translate_design(v, seen, path + (k,)) for k, v in value.items()}
    if isinstance(value, list):
        return [translate_design(v, seen, path + (i,)) for i, v in enumerate(value)]
    if isinstance(value, str) and path[-1] in ('text', 'altText', 'preheaderText'):
        return translate_html(value, seen) if path[-1] == 'text' else translate_text(value, seen)
    return value


def js_json(value):
    # PHP json_encode-compatible JSON string layer used by existing CRM storage.
    return json.dumps(value, ensure_ascii=True, separators=(',', ':')).replace('/', r'\/')


def flatten(value, path=()):
    if isinstance(value, dict):
        for k, v in value.items():
            yield from flatten(v, path + (k,))
    elif isinstance(value, list):
        for i, v in enumerate(value):
            yield from flatten(v, path + (i,))
    else:
        yield path, value


def validate_html(source, target):
    a, b = Nodes(source), Nodes(target)
    # No tag or attribute change except localized accessibility text and language.
    def skeleton(nodes):
        return [(kind, tag, [(k, v) for k, v in attrs if k not in ('alt', 'title', 'lang', 'xml:lang')] if attrs is not None else None) for kind, tag, attrs in nodes.tags]
    assert skeleton(a) == skeleton(b), 'HTML markup changed'
    # Remove only authorized changed node/attribute spans, then compare raw markup.
    def raw_markup(s, n):
        edits = [(x,y,'') for x,y in n.ranges]
        edits += [(x,y,'<LOCALIZED>') for x,y,k,v in n.attrs if k in ('alt','title')]
        for x,y,v in sorted(edits,reverse=True):s=s[:x]+v+s[y:]
        return s.replace(' lang="pt-BR"','')
    assert raw_markup(source,a) == raw_markup(target,b), 'Raw non-text HTML changed'
    urls = lambda s: re.findall(r'(?:https?://|mailto:)[^\s<>"\']+', s)
    assert urls(source) == urls(target), 'URLs changed'
    numbers = lambda s: re.findall(r'\d+(?:[.,]\d+)?', s)
    assert numbers(source) == numbers(target), 'Numbers changed'
    fields = lambda s: re.findall(r'\{\{[^{}]+\}\}|\{[A-Za-z_][^{}]*\}|\*\|[^|]+\|\*', s)
    assert fields(source) == fields(target), 'Merge fields changed'
    return {
        'tag_events': len(a.tags),
        'urls_preserved': len(urls(source)),
        'numeric_tokens_preserved': len(numbers(source)),
        'merge_fields_preserved': len(fields(source)),
        'translated_visible_nodes': sum(source[x:y] != translate_text(source[x:y], Counter()) for x,y in a.ranges),
    }


def parse_sources():
    s=(ROOT/'modules/ma/database/email_template_default.sql').read_text(encoding='utf-8')
    head=re.match(r'INSERT INTO `tblma_email_templates`\(([^)]+)\) VALUES ',s)
    assert head
    cols=[x.strip().strip('`') for x in head[1].split(',')]
    i=head.end();rows=[];raw=[]
    while i<len(s):
     while i<len(s) and s[i].isspace():i+=1
     if i==len(s):break
     assert s[i]=='(',(i,s[i:i+30]);start=i;i+=1;values=[]
     while True:
      while s[i].isspace():i+=1
      if s[i]=="'":
       i+=1;value=''
       while True:
        c=s[i];i+=1
        if c=='\\':
         c=s[i];i+=1;value+=dict(n='\n',r='\r',t='\t',b='\b',Z='\x1a').get(c,'\0' if c=='0' else c)
        elif c=="'":
         if s[i]=="'":value+="'";i+=1
         else:break
        else:value+=c
      else:
       st=i
       while s[i] not in ',)':i+=1
       value=s[st:i].strip()
      values.append(value)
      while s[i].isspace():i+=1
      if s[i]==')':i+=1;break
      assert s[i]==',';i+=1
     assert len(cols)==len(values)
     r=dict(zip(cols,values));r['line']=s[:start].count('\n')+1;rows.append(r);raw.append(s[start:i])
     while i<len(s) and s[i].isspace():i+=1
     if i==len(s):break
     if s[i]==';':i+=1;assert not s[i:].strip();break
     assert s[i]==',';i+=1
    return rows


def main():
    rows = parse_sources()
    catalogue = {'version': 1, 'locale': 'pt-BR', 'templates': []}
    qa = []
    all_seen = Counter()
    for index, row in enumerate(rows, 1):
        source_html = json.loads(row['data_html'])
        source_design = json.loads(json.loads(row['data_design']))
        seen_html, seen_design = Counter(), Counter()
        target_html = translate_html(source_html, seen_html, add_language=True)
        target_design = translate_design(source_design, seen_design)
        target = {
            'name': NAMES[index - 1], 'description': row['description'],
            'language': 'portuguese_br', 'data_html': js_json(target_html),
            'data_design': js_json(js_json(target_design)),
        }
        source = {k: row[k] for k in ('name','description','language','data_html','data_design')}
        catalogue['templates'].append({
            'source': source,
            'target': target,
            'origin_metadata': {k: row[k] for k in ('color','category','published','addedfrom','dateadded')},
        })
        result = validate_html(source_html, target_html)
        a, b = dict(flatten(source_design)), dict(flatten(target_design))
        assert a.keys() == b.keys(), 'Unlayer JSON paths changed'
        changed = []
        for path in a:
            if a[path] != b[path]:
                assert path[-1] in ('text','altText','preheaderText'), ('Unexpected design change', path)
                if path[-1] == 'text': validate_html(a[path], b[path])
                changed.append('/'.join(map(str,path)))
        # Every translated design segment must be represented in final HTML.
        assert set(seen_design) <= set(seen_html), set(seen_design) - set(seen_html)
        assert set(seen_html) - set(seen_design) <= {'Video'}, set(seen_html) - set(seen_design)
        assert json.loads(target['data_html']) == target_html
        assert json.loads(json.loads(target['data_design'])) == target_design
        result.update(template=index, source_name=row['name'], target_name=target['name'],
                      changed_design_paths=changed, translated_segments=sorted(seen_html),
                      source_html_sha256=hashlib.sha256(row['data_html'].encode()).hexdigest(),
                      source_design_sha256=hashlib.sha256(row['data_design'].encode()).hexdigest())
        qa.append(result)
        all_seen.update(seen_html)
    import argparse
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--write', action='store_true', help='Regenerate the reviewed catalogue locally')
    parser.add_argument('--check', action='store_true', help='Validate the checked-in catalogue (default)')
    args = parser.parse_args()
    if args.write:
        OUT.parent.mkdir(parents=True, exist_ok=True)
        OUT.write_text(json.dumps(catalogue, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    else:
        assert json.loads(OUT.read_text(encoding='utf-8')) == catalogue, 'Catalogue differs from reviewed source/text generation'
    print(f'MA catalogue: {len(qa)} defaults, {len(all_seen)} translated segments; HTML/design/URLs/numbers/source bytes validated.')

if __name__ == '__main__':main()
