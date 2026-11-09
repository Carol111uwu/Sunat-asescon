                                                 
from __future__ import annotations
import re
from acceso_sol import frames

def siguiente_pagina_sol(page):
                                                                                      
    selectores=(
        'button.mat-paginator-navigation-next',
        'button[aria-label*="siguiente" i]',
        'button[aria-label*="next page" i]',
        '.p-paginator-next',
        '.pagination a[rel="next"]',
        '.pagination button[aria-label*="next" i]',
        'a[rel="next"]',
        '.pagination li.next a',
        'input[value="Siguiente" i]',
        'a[title="Siguiente" i]',
        'button[title="Siguiente" i]',
    )
    for f in frames(page):
        for css in selectores:
            try:
                for boton in f.locator(css).all():
                    if not boton.is_visible() or not boton.is_enabled():continue
                    if str(boton.get_attribute('aria-disabled') or '').lower()=='true':continue
                    if boton.evaluate("e => !!e.closest('[aria-disabled=\"true\"], .disabled, .inactive')"):continue
                    clase=(boton.get_attribute('class') or '').lower()
                    if re.search(r'\b(?:disabled|inactive)\b',clase):continue
                    return boton
            except Exception:continue
    return None

