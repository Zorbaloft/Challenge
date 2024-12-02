import "./bootstrap.js";
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import "./styles/app.css";
import { Modal } from "bootstrap";

console.log("This log comes from assets/app.js - welcome to AssetMapper! 🎉");


 
document.addEventListener('turbo:load', function() {
    const categorySelect = document.getElementById('categorySelect');
    const galleries = document.querySelectorAll('.category-gallery');

    categorySelect.addEventListener('change', function() {
        const selectedCategoryId = this.value;

        galleries.forEach(gallery => {
            if (selectedCategoryId === '' || gallery.dataset.categoryId === selectedCategoryId) {
                gallery.style.display = 'block';
            } else {
                gallery.style.display = 'none';
            }
        });
    });

    // Trigger change event to show the initial state
    categorySelect.dispatchEvent(new Event('change'));
});