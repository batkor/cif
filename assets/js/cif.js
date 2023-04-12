(function (Drupal, Cropper, once) {

  /**
   * Implements function for integrate cropper.
   * @constructor
   *
   * @param {Element} elem
   *   The target element.
   */
  function Cif(elem) {
    this.elem = elem;
    this.uploadName = null;
    this.addUploadButton();
  }

  Cif.prototype.getAspectRatio = function () {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = (e) => reject(e);
      img.src = this.elem.getAttribute('src');
    })
  }

  Cif.prototype.getData = function () {
    if (!this.data) {
      this.data = JSON.parse(atob(this.elem.getAttribute('cropper-data')));
    }

    return this.data;
  }

  Cif.prototype.showPopup = function (status, src) {
    this.getImgElem().src = src || '';
    document.querySelector('.cropper_popup').classList.toggle('visually-hidden', !status);
    document.body.classList.toggle('cropper_popup_open', status);
    if (status) {
      this.initPopupHandlers();
      this.getAspectRatio().then(img => {
        this.cropper = new Cropper(this.getImgElem(), {
          aspectRatio: img.naturalWidth / img.naturalHeight,
          viewMode: 2,
        });
      })
    }
    else {
      this.cropper.destroy();
      this.cropper = null;
    }
  }

  Cif.prototype.getImgElem = function () {
    return document.getElementById('cropper_popup_img');
  }

  Cif.prototype.addUploadButton = function () {
    let btn = document.createElement('div')
    btn.role = 'button';
    btn.classList.add('upload-submit');
    btn.ariaLabel = btn.title = 'Upload';
    this.elem.parentElement.append(btn);
    this.elem.parentElement.classList.add('cropper_wrapper');
    btn.addEventListener('click', () => {
      let input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      input.click();
      input.onchange = (e) => {
        Object.keys(e.target.files).forEach(i => {
          this.uploadName = e.target.files[i].name;
          this.showPopup(true, URL.createObjectURL(e.target.files[i]))
        })
      }
    })
  }

  Cif.prototype.initPopupHandlers = function () {
    if (this.popupHandlerOnce) {
      return;
    }

    this.popupHandlerOnce = true;
    document
      .querySelectorAll('.cropper_popup .cropper_popup [name="cancel"]')
      .forEach(el => {
        el.addEventListener('click', e => {
          this.showPopup(false);
        }, {once: true})
      })

    document
      .querySelectorAll('.cropper_popup [name="crop"]')
      .forEach(el => {
        el.addEventListener('click', e => {
          let canvas = this.cropper.getCroppedCanvas();
          this.elem.setAttribute('src', canvas.toDataURL())

          if (this.elem.hasAttribute('srcset')) {
            this.elem.setAttribute('srcset', this.elem.getAttribute('src'));
          }

          this.showPopup(false);
          canvas.toBlob(blob => {
            let data = new FormData();
            data.append('image', blob, this.uploadName);
            data.append('data', this.elem.getAttribute('cropper-data'));
            fetch('/session/token', {
              type: 'GET',
            })
              .then(response => response.text())
              .then(token => {
                fetch('/cif/upload', {
                  method: "POST",
                  body: data,
                  headers: {
                    'X-CSRF-Token': token,
                  }
                })
                  .then(response => response.text())
                  .then(text => console.log(text))
              })
          })
        }, {once: true})
      })
  }

  Drupal.behaviors.cif = {
    attach: function (context) {
      once('cropper_init', '[cropper-data]', context || document).forEach(el => {
        el.cif = new Cif(el);
      })
    }
  }

}(Drupal, Cropper, once))
