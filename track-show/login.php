<? if (!$include_flag){exit();} ?>
<script>
  $(document).ready(function() 
  {
    $('input[name="email"]').focus(); 
  });
</script>

<div class="row">
    <div class="col-sm-6 col-md-4 col-md-offset-4">
        <div class="account-wall">
            <img class="profile-img" src="img/icons/photo.png">
            <form class="form-signin" action='' method="POST">
              <input type=hidden name='page' value='login'>
              <input type=hidden name='act' value='login'>
              <input type="text" class="form-control" id="email" name="email" placeholder="E-mail" required autofocus>
              <input type="password" class="form-control" id="password" name="password" placeholder="Пароль" required>
              <button class="btn btn-lg btn-primary btn-block" type="submit">Войти</button>
            </form>
        </div>
    </div>
</div>