/**
 * AliProfi Content Protection 2.0
 * Добавляет атрибуцию и заменяет кириллические символы на схожие латинские
 * при копировании текста (минимум 20 символов).
 */

(() => {
    'use strict';

    const MIN = 20;               // минимальная длина выделения
    const ATTR = '\n\nИсточник (AliProfi.ru) 🔗 ';

    /* Только конфликтные символы */
    const map = { 'С':'C','с':'c','Е':'E','е':'e','Т':'T','т':'t','О':'O','о':'o','Р':'P','р':'p','А':'A','а':'a','Н':'H','н':'h','К':'K','к':'k','Х':'X','х':'x','В':'B','в':'b','М':'M','м':'m' };

    const onCopy = e => {

        const sel = window.getSelection().toString().trim();
        if ( sel.length < MIN ) return;

        /* Сохраняем ссылки, чтобы не искажать их */
        const urls = [];
        let txt = sel.replace(/https?:\/\/[^\s]+/gi, m => {
            urls.push(m);
            return `__URL${urls.length - 1}__`;
        });

        /* Замена символов */
        for ( const k in map ) {
            txt = txt.split(k).join(map[k]);
        }

        /* Возвращаем ссылки */
        urls.forEach( (url, i) => {
            txt = txt.replace(`__URL${i}__`, url);
        });

        const final = txt + ATTR + location.href;

        /* Копируем в буфер */
        const div = document.createElement('div');
        div.style.position = 'fixed';
        div.style.left = '-9999px';
        div.textContent = final;
        document.body.appendChild(div);

        const range = document.createRange();
        range.selectNodeContents(div);
        const selObj = window.getSelection();
        selObj.removeAllRanges();
        selObj.addRange(range);

        setTimeout(() => div.remove(), 50);
    };

    document.addEventListener('copy', onCopy);
})();
